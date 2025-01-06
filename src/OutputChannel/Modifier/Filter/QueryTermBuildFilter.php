<?php

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace DsLuceneBundle\OutputChannel\Modifier\Filter;

use DsLuceneBundle\Modifier\TermModifier;
use DynamicSearchBundle\OutputChannel\Allocator\OutputChannelAllocatorInterface;
use DynamicSearchBundle\OutputChannel\Modifier\OutputChannelModifierFilterInterface;

class QueryTermBuildFilter implements OutputChannelModifierFilterInterface
{
    public function __construct(protected TermModifier $termModifier)
    {
    }

    public function dispatchFilter(OutputChannelAllocatorInterface $outputChannelAllocator, array $options): mixed
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

        return implode(' OR ', array_filter($query, static function ($value) {
            return !is_null($value);
        }));
    }

    protected function addPhraseQuery(string $cleanTerm, array $options): string
    {
        $exactTerm = sprintf('"%s"', $cleanTerm);
        $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $exactTerm);

        return sprintf('(%s)', implode(' OR ', $fieldTerms));
    }

    protected function addFuzzyQuery(string $cleanTerm, array $options): ?string
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
            $terms[] = implode(' OR ', $subTerms);
        }

        if (count($terms) === 0) {
            return null;
        }

        return sprintf('(%s)', implode(' OR ', $terms));
    }

    protected function addWildcardQuery(string $cleanTerm, array $options): ?string
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
            $terms[] = implode(' OR ', $subTerms);
        }

        if (count($terms) === 0) {
            return null;
        }

        return sprintf('(%s)', implode(' OR ', $terms));
    }

    protected function addSimpleQuery(string $cleanTerm, array $options): ?string
    {
        $cleanSplitTerms = $this->termModifier->removeSpecialOperators($cleanTerm);
        $splitTerms = $this->termModifier->splitTerm($cleanSplitTerms, $options['min_prefix_length'], 10);

        $terms = [];

        if (count($options['restrict_search_fields']) === 0) {
            return sprintf('(%s)', implode(' ', $splitTerms));
        }

        foreach ($splitTerms as $i => $queryTerm) {
            $fieldTerms = $this->getFieldTerm($options['restrict_search_fields'], $queryTerm);
            $subTerms = [];
            foreach ($fieldTerms as $fieldTerm) {
                $subTerms[] = $fieldTerm;
            }
            $terms[] = implode(' OR ', $subTerms);
        }

        if (count($terms) === 0) {
            return null;
        }

        return sprintf('(%s)', implode(' OR ', $terms));
    }

    protected function getFieldTerm(array $restrictedSearchFields, string $term): array
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
