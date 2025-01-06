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
use ZendSearch\Lucene;
use ZendSearch\Lucene\Search\Query;
use ZendSearch\Lucene\Search\QueryParser;

class SearchOutputChannel implements OutputChannelInterface
{
    protected array $options;
    protected OutputChannelContextInterface $outputChannelContext;
    protected LuceneStorageBuilder $storageBuilder;
    protected OutputChannelModifierEventDispatcher $eventDispatcher;

    public function __construct(LuceneStorageBuilder $storageBuilder)
    {
        $this->storageBuilder = $storageBuilder;
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            'min_prefix_length',
            'result_limit',
            'phrased_search',
            'fuzzy_search',
            'wildcard_search',
            'restrict_search_fields'
        ]);

        $resolver->setDefaults([
            'min_prefix_length'      => 3,
            'result_limit'           => 1000,
            'phrased_search'         => false,
            'fuzzy_search'           => false,
            'wildcard_search'        => false,
            'restrict_search_fields' => []
        ]);

        $resolver->setAllowedTypes('min_prefix_length', ['int']);
        $resolver->setAllowedTypes('phrased_search', ['bool']);
        $resolver->setAllowedTypes('fuzzy_search', ['bool']);
        $resolver->setAllowedTypes('wildcard_search', ['bool']);
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

    public function getResult(SearchContainerInterface $searchContainer): SearchContainerInterface
    {
        $query = $searchContainer->getQuery();

        if (!$query instanceof Query\Boolean) {
            return $searchContainer;
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

        $result = $eventData->getParameter('result');

        $searchContainer->result->setData($result);
        $searchContainer->result->setHitCount(count($result));

        return $searchContainer;
    }
}
