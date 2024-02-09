<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\MultiOutputChannelInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use DynamicSearchBundle\OutputChannel\Query\MultiSearchContainerInterface;
use DynamicSearchBundle\OutputChannel\Query\SearchContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene;

class MultiSearchOutputChannel implements OutputChannelInterface, MultiOutputChannelInterface
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
        throw new \Exception('not allowed');
    }

    public function getResult(SearchContainerInterface $searchContainer): SearchContainerInterface
    {
        throw new \Exception('not allowed');
    }

    public function getMultiSearchResult(MultiSearchContainerInterface $multiSearchContainer): MultiSearchContainerInterface
    {
        $userLocale = $this->outputChannelContext->getRuntimeQueryProvider()->getUserLocale();
        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        foreach ($multiSearchContainer->getSearchContainer() as $searchContainer) {

            $query = $searchContainer->getQuery();

            $eventData = $this->eventDispatcher->dispatchAction('build_index', [
                'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE, $userLocale)
            ]);

            /** @var Lucene\SearchIndexInterface $index */
            $index = $eventData->getParameter('index');

            $result = $index->find($query);

            $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
                'result' => $result,
            ]);

            $result = $eventData->getParameter('result');

            $searchContainer->result->setData($result);
            $searchContainer->result->setHitCount(count($result));
        }

        return $multiSearchContainer;
    }
}
