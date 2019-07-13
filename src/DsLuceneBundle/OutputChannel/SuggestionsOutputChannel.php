<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\RuntimeOptions\RuntimeOptionsProviderInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuggestionsOutputChannel implements OutputChannelInterface
{
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
    public function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setRequired([
            'min_prefix_length',
            'result_limit',
            'only_last_word_wildcard',
            'multiple_words_operator',
            'restrict_search_fields',
            'restrict_search_fields_operator',
        ]);

        $optionsResolver->setDefaults([
            'min_prefix_length'               => 3,
            'result_limit'                    => 10,
            'only_last_word_wildcard'         => false,
            'multiple_words_operator'         => 'OR',
            'restrict_search_fields'          => [],
            'restrict_search_fields_operator' => 'OR'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $indexProviderOptions, array $options = [], array $contextOptions = []): array
    {
        $queryTerm = $this->runtimeOptionsProvider->getUserQuery();

        $eventData = $this->eventDispatcher->dispatchAction('pre_execute', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var \Zend_Search_Lucene $index */
        $index = $eventData->getParameter('index');

        $cleanTerm = $this->eventDispatcher->dispatchFilter(
            'query.clean_term',
            ['raw_term' => $queryTerm]
        );

        $eventData = $this->eventDispatcher->dispatchAction('post_query_parse', [
            'clean_term'        => $cleanTerm,
            'parsed_query_term' => $this->parseQuery($cleanTerm, $options)
        ]);

        $parsedQueryTerm = $eventData->getParameter('parsed_query_term');

        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength($options['min_prefix_length']);

        // we need to check each term:
        // - to check if its really available within sub-queries
        // - to do so, one item should be enough to validate
        \Zend_Search_Lucene::setResultSetLimit($options['result_limit']);

        $query = new \Zend_Search_Lucene_Search_Query_Boolean();
        $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($parsedQueryTerm, 'utf-8');

        $query->addSubquery($userQuery, true);

        $eventData = $this->eventDispatcher->dispatchAction('post_query_build', [
            'query' => $query,
            'term'  => $cleanTerm
        ]);

        $hits = $index->find($eventData->getParameter('query'));

        $suggestions = [];
        foreach ($hits as $hit) {
            $suggestions[] = $hit;
        }

        $eventData = $this->eventDispatcher->dispatchAction('post_execute', [
            'result' => $suggestions,
        ]);

        return $eventData->getParameter('result');
    }

    /**
     * @param string $query
     * @param array  $options
     *
     * @return string
     */
    protected function parseQuery(string $query, array $options)
    {
        $minPrefixLength = $options['min_prefix_length'];
        $onlyLastWordWildCard = $options['only_last_word_wildcard'];
        $multipleWordsOperator = $options['multiple_words_operator'];
        $multipleFieldsOperator = $options['restrict_search_fields_operator'];

        $queryTerms = array_values(array_filter(explode(' ', $query), function ($t) use ($minPrefixLength) {
            return strlen($t) >= $minPrefixLength;
        }));

        $terms = [];
        foreach ($queryTerms as $i => $queryTerm) {
            if ($i === count($queryTerms) - 1) {
                $parsedWildCardTerm = $this->checkWildcardTerm($queryTerm);
                $terms[] = sprintf('+%s*', $parsedWildCardTerm);
            } else {
                $terms[] = sprintf('+%s%s', $queryTerm, ($onlyLastWordWildCard ? '' : '*'));
            }
        }

        if (count($options['restrict_search_fields']) > 0) {
            $fieldTerms = [];
            foreach ($options['restrict_search_fields'] as $field) {
                $fieldTerms[] = sprintf('(%s:%s)', $field, join(' ', $terms));
            }
            $query = join(sprintf(' %s ', $multipleFieldsOperator), $fieldTerms);
        } else {
            $query = join(sprintf(' %s ', $multipleWordsOperator), $terms);
        }

        return $query;
    }

    /**
     * @param string $term
     *
     * @return string|null
     */
    protected function checkWildcardTerm(string $term)
    {
        $cleanTerm = null;
        $specialTerms = [];

        preg_match_all('/[\p{L}\p{N}]+/u', $term, $match, PREG_OFFSET_CAPTURE);

        if (!is_array($match[0])) {
            return $term;
        }

        foreach ($match[0] as $matchTerm) {
            $specialTerms[] = $matchTerm[0];
        }

        $cleanTerm = join('?', $specialTerms);

        return $cleanTerm;
    }
}
