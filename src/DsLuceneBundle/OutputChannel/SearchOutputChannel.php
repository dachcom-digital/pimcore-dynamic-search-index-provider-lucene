<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene\Search\QueryParser;

class SearchOutputChannel implements OutputChannelInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var OutputChannelContextInterface
     */
    protected $outputChannelContext;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @param LuceneStorageBuilder $storageBuilder
     */
    public function __construct(LuceneStorageBuilder $storageBuilder)
    {
        $this->storageBuilder = $storageBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setRequired([
            'min_prefix_length',
            'result_limit',
            'fuzzy_search',
            'wildcard_search',
            'restrict_search_fields'
        ]);

        $optionsResolver->setDefaults([
            'min_prefix_length'      => 3,
            'result_limit'           => 1000,
            'fuzzy_search'           => true,
            'wildcard_search'        => true,
            'restrict_search_fields' => []
        ]);

        $optionsResolver->setAllowedTypes('min_prefix_length', ['int']);
        $optionsResolver->setAllowedTypes('fuzzy_search', ['bool']);
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext)
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        $queryTerm = $this->outputChannelContext->getRuntimeQueryProvider()->getUserQuery();

        $cleanTerm = $this->eventDispatcher->dispatchFilter(
            'query.clean_term',
            [
                'raw_term'               => $queryTerm,
                'output_channel_options' => $this->options
            ]
        );

        $builtTerm = $this->eventDispatcher->dispatchFilter(
            'query.build_term',
            [
                'clean_term'             => $cleanTerm,
                'output_channel_options' => $this->options
            ]
        );

        if (empty($builtTerm)) {
            return null;
        }

        $eventData = $this->eventDispatcher->dispatchAction('post_query_parse', [
            'clean_term' => $cleanTerm,
            'built_term' => $builtTerm
        ]);

        $parsedQueryTerm = $eventData->getParameter('built_term');

        Query\Wildcard::setMinPrefixLength($this->options['min_prefix_length']);

        $query = new Query\Boolean();
        $userQuery = QueryParser::parse($parsedQueryTerm, 'utf-8');

        $query->addSubquery($userQuery, true);

        $eventData = $this->eventDispatcher->dispatchAction('post_query_build', [
            'query' => $query,
            'term'  => $cleanTerm
        ]);

        return $eventData->getParameter('query');
    }

    /**
     * {@inheritdoc}
     */
    public function getResult($query)
    {
        if (!$query instanceof Query\Boolean) {
            return [];
        }

        $userLocale = $this->outputChannelContext->getRuntimeQueryProvider()->getUserLocale();
        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        $eventData = $this->eventDispatcher->dispatchAction('build_index', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE, $userLocale)
        ]);

        /** @var Lucene\SearchIndexInterface $index */
        $index = $eventData->getParameter('index');

        $result = $index->find($query);

        if (count($result) > $this->options['result_limit']) {
            $result = array_slice($result, 0, $this->options['result_limit']);
        }

        $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
            'result' => $result,
        ]);

        return $eventData->getParameter('result');
    }

    /**
     * {@inheritdoc}
     */
    public function getHitCount($result)
    {
        return count($result);
    }
}
