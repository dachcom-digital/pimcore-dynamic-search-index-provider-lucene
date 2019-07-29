<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\RuntimeOptions\RuntimeOptionsProviderInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchOutputChannel implements OutputChannelInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $indexProviderOptions;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var RuntimeOptionsProviderInterface
     */
    protected $runtimeOptionsProvider;

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
            'max_per_page'
        ]);

        $optionsResolver->setDefaults([
            'min_prefix_length' => 3,
            'max_per_page'      => 10
        ]);
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
    public function setIndexProviderOptions(array $indexProviderOptions)
    {
        $this->indexProviderOptions = $indexProviderOptions;
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
    public function setRuntimeParameterProvider(RuntimeOptionsProviderInterface $runtimeOptionsProvider)
    {
        $this->runtimeOptionsProvider = $runtimeOptionsProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        $queryTerm = $this->runtimeOptionsProvider->getUserQuery();

        $cleanTerm = $this->eventDispatcher->dispatchFilter(
            'query.clean_term',
            ['raw_term' => $queryTerm]
        );

        $eventData = $this->eventDispatcher->dispatchAction('post_query_parse', [
            'clean_term'        => $cleanTerm,
            'parsed_query_term' => $this->parseQuery($cleanTerm)
        ]);

        $parsedQueryTerm = $eventData->getParameter('parsed_query_term');

        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength($this->options['min_prefix_length']);

        // we need to set result limit to 1000
        // lucene does not have any offset feature
        // each hit returns a "lazy-loaded" document
        // so we need to get rid of paging in a later process
        \Zend_Search_Lucene::setResultSetLimit(1000);

        $query = new \Zend_Search_Lucene_Search_Query_Boolean();
        $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($parsedQueryTerm, 'utf-8');

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
        if (!$query instanceof \Zend_Search_Lucene_Search_Query_Boolean) {
            return [];
        }

        $eventData = $this->eventDispatcher->dispatchAction('build_index', [
            'index' => $this->storageBuilder->getLuceneIndex($this->indexProviderOptions['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var \Zend_Search_Lucene $index */
        $index = $eventData->getParameter('index');

        $result = $index->find($query);

        $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
            'result' => $result,
        ]);

        return $eventData->getParameter('result');

    }

    /**
     * {@inheritdoc}
     */
    public function getHits($result)
    {
        if (!is_array($result)) {
            return [];
        }

        $hits = $result;

        $eventData = $this->eventDispatcher->dispatchAction('post_hits_execute', [
            'hits' => $hits,
        ]);

        return $eventData->getParameter('hits');

    }

    /**
     * @param string $query
     *
     * @return string
     */
    protected function parseQuery(string $query)
    {
        return $query;
    }
}
