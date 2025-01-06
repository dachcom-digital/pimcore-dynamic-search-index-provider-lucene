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

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use DynamicSearchBundle\OutputChannel\Query\SearchContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Exception\ExceptionInterface;
use ZendSearch\Lucene;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene\Search\QueryParser;

class AutoCompleteOutputChannel implements OutputChannelInterface
{
    protected array $options;
    protected OutputChannelContextInterface $outputChannelContext;
    protected OutputChannelModifierEventDispatcher $eventDispatcher;

    public function __construct(protected LuceneStorageBuilder $storageBuilder)
    {
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'min_prefix_length',
            'use_fuzzy_term_search_fallback',
            'fuzzy_default_prefix_length',
            'fuzzy_similarity',
        ]);

        $resolver->setDefaults([
            'min_prefix_length'              => 3,
            'use_fuzzy_term_search_fallback' => true,
            'fuzzy_default_prefix_length'    => 0,
            'fuzzy_similarity'               => 0.5,
        ]);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext): void
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getQuery(): mixed
    {
        $queryTerm = $this->outputChannelContext->getRuntimeQueryProvider()->getUserQuery();
        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        $eventData = $this->eventDispatcher->dispatchAction('pre_execute', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var Lucene\SearchIndexInterface $index */
        $index = $eventData->getParameter('index');

        $cleanTerm = $this->eventDispatcher->dispatchFilter(
            'query.clean_term',
            [
                'raw_term'               => $queryTerm,
                'output_channel_options' => $this->options
            ]
        );

        Query\Wildcard::setMinPrefixLength($this->options['min_prefix_length']);

        $terms = $this->getWildcardTerms($cleanTerm, $index);
        if (count($terms) === 0 && $this->options['use_fuzzy_term_search_fallback'] === true) {
            $terms = $this->getFuzzyTerms($cleanTerm, $index, $this->options['fuzzy_default_prefix_length'], $this->options['fuzzy_similarity']);
        }

        $eventData = $this->eventDispatcher->dispatchAction('post_wildcard_terms', [
            'terms' => $terms
        ]);

        return $eventData->getParameter('terms');
    }

    public function getResult(SearchContainerInterface $searchContainer): SearchContainerInterface
    {
        $query = $searchContainer->getQuery();

        if (!is_array($query)) {
            return $searchContainer;
        }

        $terms = $query;

        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        $eventData = $this->eventDispatcher->dispatchAction('pre_execute', [
            'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
        ]);

        /** @var Lucene\SearchIndexInterface $index */
        $index = $eventData->getParameter('index');

        // we need to check each term:
        // - to check if its really available within sub-queries
        // - to do so, one item should be enough to validate
        Lucene\Lucene::setResultSetLimit(1);

        $eventData = $this->eventDispatcher->dispatchAction('post_wildcard_terms', [
            'terms' => $terms
        ]);

        $terms = $eventData->getParameter('terms');

        $suggestions = [];
        /** @var Lucene\Index\Term $term */
        foreach ($terms as $term) {
            $fieldText = $term->text;

            if (in_array($fieldText, $suggestions, true)) {
                continue;
            }

            $query = new Query\Boolean();
            $userQuery = QueryParser::parse($fieldText, 'utf-8');

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

        $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
            'result' => $suggestions,
        ]);

        $result = $eventData->getParameter('result');

        $searchContainer->result->setData($result);
        $searchContainer->result->setHitCount(count($result));

        return $searchContainer;
    }

    protected function getWildcardTerms(string $queryStr, Lucene\SearchIndexInterface $index): array
    {
        $pattern = new Lucene\Index\Term($queryStr . '*');
        $userQuery = new Query\Wildcard($pattern);

        try {
            $terms = $userQuery->rewrite($index)->getQueryTerms();
        } catch (ExceptionInterface $e) {
            return [];
        }

        return $terms;
    }

    public function getFuzzyTerms(string $queryStr, Lucene\SearchIndexInterface $index, int $prefixLength = 0, float $similarity = 0.5): array
    {
        Query\Fuzzy::setDefaultPrefixLength($prefixLength);
        $term = new Lucene\Index\Term($queryStr);

        try {
            $fuzzyQuery = new Query\Fuzzy($term, $similarity);
        } catch (ExceptionInterface $e) {
            return [];
        }

        try {
            $terms = $fuzzyQuery->rewrite($index)->getQueryTerms();
        } catch (ExceptionInterface $e) {
            return [];
        }

        return $terms;
    }
}
