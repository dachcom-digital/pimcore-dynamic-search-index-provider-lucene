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

        $enableFuzzySearch = $outputChannelOptions['fuzzy_search'];
        $enableWildcardSearch = $outputChannelOptions['wildcard_search'];
        $enablePhrasedSearch = $outputChannelOptions['phrased_search'];

        // Without restricted search fields:    ("awesome query") OR (awesome~ OR query~);
        // With restricted search fields:       (f1:"awesome query" OR f2:"awesome query") OR (f1:awesome~ OR f2:awesome~ OR f1:query~ OR f2:query~);

        $query = [];

        // optional: search for exact match
        if ($enablePhrasedSearch === true) {
            $query[] = $this->addPhraseQuery($cleanTerm, $outputChannelOptions);
        }

        // optional: search for each fuzzy term
        if ($enableFuzzySearch === true) {
            $query[] = $this->addFuzzyQuery($cleanTerm, $outputChannelOptions);
        }

        // optional: search for each wildcard term
        if ($enableWildcardSearch === true) {
            $query[] = $this->addWildcardQuery($cleanTerm, $outputChannelOptions);
        }

        // add simple query if fuzzy and wild card search has been disabled
        if ($enableFuzzySearch === false && $enableWildcardSearch === false) {
            $query[] = $this->addSimpleQuery($cleanTerm, $outputChannelOptions);
        }

        $query = join(' OR ', $query);

        return $query;
    }

    /**
     * @param string $cleanTerm
     * @param array  $options
     *
     * @return string
     */
    protected function addPhraseQuery($cleanTerm, array $options)
    {
        $exactTerm = sprintf('"%s"', $cleanTerm);
        $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $exactTerm);

        return sprintf('(%s)', join(' OR ', $fieldTerms));
    }

    /**
     * @param string $cleanTerm
     * @param array  $options
     *
     * @return string
     */
    protected function addFuzzyQuery($cleanTerm, array $options)
    {
        // do not allow min prefix length <= 4 in fuzzy search: performance hell!
        $minPrefixLength = max(4, $options['min_prefix_length']);

        $terms = [];
        $splitTerms = $this->termModifier->splitTerm($cleanTerm, $minPrefixLength, 10);

        foreach ($splitTerms as $i => $queryTerm) {
            $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $queryTerm);
            $subTerms = [];
            foreach ($fieldTerms as $fieldTerm) {
                $subTerms[] = sprintf('%s~', $fieldTerm);
            }
            $terms[] = join(' OR ', $subTerms);
        }

        return sprintf('(%s)', join(' OR ', $terms));
    }

    /**
     * @param string $cleanTerm
     * @param array  $options
     *
     * @return string
     */
    protected function addWildcardQuery($cleanTerm, array $options)
    {
        // do not allow min prefix length <= 4 in wildcard search: performance hell!
        $minPrefixLength = max(4, $options['min_prefix_length']);

        $terms = [];
        $splitTerms = $this->termModifier->splitTerm($cleanTerm, $minPrefixLength, 10);

        foreach ($splitTerms as $i => $queryTerm) {
            $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $queryTerm);
            $subTerms = [];
            foreach ($fieldTerms as $fieldTerm) {
                $subTerms[] = sprintf('%s*', $fieldTerm);
            }
            $terms[] = join(' OR ', $subTerms);
        }

        return sprintf('(%s)', join(' OR ', $terms));
    }

    /**
     * @param string $cleanTerm
     * @param array  $options
     *
     * @return string
     */
    protected function addSimpleQuery($cleanTerm, array $options)
    {
        $cleanSplitTerms = $this->termModifier->removeSpecialOperators($cleanTerm);
        $splitTerms = $this->termModifier->splitTerm($cleanSplitTerms, $options['min_prefix_length'], 10);

        $terms = [];

        if (count($options['restrict_search_fields']) === 0) {
            return sprintf('(%s)', join(' ', $splitTerms));
        }

        foreach ($splitTerms as $i => $queryTerm) {
            $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $queryTerm);
            $subTerms = [];
            foreach ($fieldTerms as $fieldTerm) {
                $subTerms[] = $fieldTerm;
            }
            $terms[] = join(' OR ', $subTerms);
        }

        return sprintf('(%s)', join(' OR ', $terms));
    }

    /**
     * @param array  $restrictedSearchFields
     * @param string $term
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
