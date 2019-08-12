<?php

namespace DsLuceneBundle\OutputChannel\Modifier\Filter;

use DsLuceneBundle\Modifier\TermModifier;
use DynamicSearchBundle\OutputChannel\Allocator\OutputChannelAllocatorInterface;
use DynamicSearchBundle\OutputChannel\Modifier\OutputChannelModifierFilterInterface;

class QueryTermBuildFilter implements OutputChannelModifierFilterInterface
{
    /**
     * @var TermModifier
     */
    protected $termModifier;

    /**
     * @param TermModifier $termModifier
     */
    public function __construct(TermModifier $termModifier)
    {
        $this->termModifier = $termModifier;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatchFilter(OutputChannelAllocatorInterface $outputChannelAllocator, array $options)
    {
        $cleanTerm = $options['clean_term'];

        // check if string is phrased
        if ($this->termModifier->isPhrasedQuery($cleanTerm) === true) {
            return $cleanTerm;
        }

        $cleanTerm = trim($this->termModifier->escapeSpecialChars($cleanTerm));

        $outputChannelOptions = $options['output_channel_options'];
        $minPrefixLength = $outputChannelOptions['min_prefix_length'];
        $restrictSearchFields = $outputChannelOptions['restrict_search_fields'];
        $enableFuzzySearch = $outputChannelOptions['fuzzy_search'];

        // Without restricted search fields:    ("awesome query") OR (awesome~ OR query~);
        // With restricted search fields:       (f1:"awesome query" OR f2:"awesome query") OR (f1:awesome~ OR f2:awesome~ OR f1:query~ OR f2:query~);

        $query = [];

        // 1. search for exact match
        $exactTerm = sprintf('"%s"', $cleanTerm);
        $fieldTerms = $this->getFieldTerm($restrictSearchFields, $exactTerm);
        $query[] = sprintf('(%s)', join(' OR ', $fieldTerms));

        // 2. OR search for each fuzzy term
        if ($enableFuzzySearch === true) {

            $terms = [];
            $splitTerms = $this->termModifier->splitTerm($cleanTerm, $minPrefixLength, 10);
            foreach ($splitTerms as $i => $queryTerm) {
                $fieldTerms = $this->getFieldTerm($restrictSearchFields, $queryTerm);
                $subTerms = [];
                foreach ($fieldTerms as $fieldTerm) {
                    $subTerms[] = sprintf('%s~', $fieldTerm);
                }
                $terms[] = join(' OR ', $subTerms);
            }

            $query[] = sprintf('(%s)', join(' OR ', $terms));

        } else {
            $query[] = sprintf('(%s)', $this->termModifier->removeSpecialOperators($cleanTerm));
        }

        $query = join(' OR ', $query);

        return $query;

    }

    /**
     * @param array $restrictedSearchFields
     * @param       $term
     *
     * @return array
     */
    protected function getFieldTerm(array $restrictedSearchFields, $term)
    {
        if (count($restrictedSearchFields) === 0) {
            return [$term];
        }

        $fieldTerms = [];
        foreach ($restrictedSearchFields as $field) {
            $fieldTerms[] = sprintf('%s:%s', $field, $term);
        }

        return $fieldTerms;
    }
}
