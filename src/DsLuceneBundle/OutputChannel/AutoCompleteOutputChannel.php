<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use DynamicSearchBundle\OutputChannel\RuntimeOptions\RuntimeOptionsProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutoCompleteOutputChannel implements OutputChannelInterface
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
     * {@inheritDoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritDoc}
     */
    public function setRuntimeParameterProvider(RuntimeOptionsProviderInterface $runtimeOptionsProvider)
    {
        $this->runtimeOptionsProvider = $runtimeOptionsProvider;
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $optionsResolver)
    {
        $optionsResolver->setRequired([
            'min_prefix_length',
            'use_fuzzy_term_search_fallback',
            'fuzzy_default_prefix_length',
            'fuzzy_similarity',
        ]);

        $optionsResolver->setDefaults([
            'min_prefix_length'              => 3,
            'use_fuzzy_term_search_fallback' => true,
            'fuzzy_default_prefix_length'    => 0,
            'fuzzy_similarity'               => 0.5,
        ]);
    }

    /**
     * @return bool
     */
    public function needsPaginator(): bool
    {
        return false;
    }

    /**
     * @return string|null
     */
    public function getPaginatorAdapterClass(): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $indexProviderOptions, array $options = [], array $contextOptions = [])
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

        $terms = $this->getWildcardTerms($cleanTerm, $index);
        if (count($terms) === 0 && $options['use_fuzzy_term_search_fallback'] === true) {
            $terms = $this->getFuzzyTerms($cleanTerm, $index, $options['fuzzy_default_prefix_length'], $options['fuzzy_similarity']);
        }

        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength($options['min_prefix_length']);

        // we need to check each term:
        // - to check if its really available within sub-queries
        // - to do so, one item should be enough to validate
        \Zend_Search_Lucene::setResultSetLimit(1);

        $eventData = $this->eventDispatcher->dispatchAction('post_wildcard_terms', [
            'terms' => $terms
        ]);

        $terms = $eventData->getParameter('terms');

        $suggestions = [];
        /** @var \Zend_Search_Lucene_Index_Term $term */
        foreach ($terms as $term) {

            $fieldText = $term->text;

            if (in_array($fieldText, $suggestions)) {
                continue;
            }

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();
            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($fieldText, 'utf-8');

            $query->addSubquery($userQuery, true);

            $eventData = $this->eventDispatcher->dispatchAction('post_query_build', [
                'query' => $query,
                'term'  => $term
            ]);

            $hits = $index->find($eventData->getParameter('query'));

            if (!is_array($hits) || count($hits) === 0) {
                continue;
            }

            $suggestions[] = $fieldText;
        }

        $eventData = $this->eventDispatcher->dispatchAction('post_execute', [
            'result' => $suggestions,
        ]);

        return $eventData->getParameter('result');

    }

    /**
     * @param string                        $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     *
     * @return array
     */
    protected function getWildcardTerms($queryStr, \Zend_Search_Lucene_Interface $index)
    {
        $pattern = new \Zend_Search_Lucene_Index_Term($queryStr . '*');
        $userQuery = new \Zend_Search_Lucene_Search_Query_Wildcard($pattern);

        try {
            $terms = $userQuery->rewrite($index)->getQueryTerms();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        return $terms;
    }

    /**
     * @param string                        $queryStr
     * @param \Zend_Search_Lucene_Interface $index
     * @param integer                       $prefixLength optionally specify prefix length, default 0
     * @param float                         $similarity   optionally specify similarity, default 0.5
     *
     * @return string[] $similarSearchTerms
     */
    public function getFuzzyTerms($queryStr, \Zend_Search_Lucene_Interface $index, $prefixLength = 0, $similarity = 0.5)
    {
        \Zend_Search_Lucene_Search_Query_Fuzzy::setDefaultPrefixLength($prefixLength);
        $term = new \Zend_Search_Lucene_Index_Term($queryStr);

        try {
            $fuzzyQuery = new \Zend_Search_Lucene_Search_Query_Fuzzy($term, $similarity);
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        try {
            $terms = $fuzzyQuery->rewrite($index)->getQueryTerms();
        } catch (\Zend_Search_Lucene_Exception $e) {
            return [];
        }

        return $terms;

    }
}