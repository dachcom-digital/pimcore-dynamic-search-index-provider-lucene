<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Storage\StorageBuilder;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Provider\OutputChannel\AutoCompleteOutputChannelInterface;

class AutoCompleteOutputChannel implements AutoCompleteOutputChannelInterface
{
    /**
     * @var StorageBuilder
     */
    protected $storageBuilder;

    /**
     * @param StorageBuilder $storageBuilder
     */
    public function __construct(StorageBuilder $storageBuilder)
    {
        $this->storageBuilder = $storageBuilder;
    }

    public function execute(ContextDataInterface $context, array $options = [])
    {
        $searchTerm = $options['query'];

        \Zend_Search_Lucene_Search_Query_Wildcard::setMinPrefixLength(2);

        $index = $this->storageBuilder->getLuceneIndex($context->getIndexProviderOptions()['database_name'], ConfigurationInterface::INDEX_BASE_STABLE);

        $terms = $this->wildcardFindTerms($searchTerm, $index);

        $suggestions = [];

        \Zend_Search_Lucene::setResultSetLimit(1);

        /** @var \Zend_Search_Lucene_Index_Term $term */
        foreach ($terms as $term) {

            $fieldText = $term->text;

            if (in_array($fieldText, $suggestions)) {
                continue;
            }

            $query = new \Zend_Search_Lucene_Search_Query_Boolean();
            $userQuery = \Zend_Search_Lucene_Search_QueryParser::parse($fieldText, 'utf-8');

            $query->addSubquery($userQuery, true);

            $hits = $index->find($query);

            if (!is_array($hits) || count($hits) === 0) {
                continue;
            }

            $suggestions[] = $fieldText;
        }

        return $suggestions;

    }

    protected function wildcardFindTerms($queryStr, \Zend_Search_Lucene_Interface $index)
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

}