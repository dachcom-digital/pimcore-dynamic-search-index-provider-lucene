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
    /**
     * @var OutputChannelContextInterface
     */
    protected $outputChannelContext;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var OutputChannelModifierEventDispatcher
     */
    protected $eventDispatcher;

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
    public static function configureOptions(OptionsResolver $optionsResolver): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext): void
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws \Exception
     */
    public function getQuery()
    {
        throw new \Exception('not allowed');
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(SearchContainerInterface $searchContainer): SearchContainerInterface
    {
        throw new \Exception('not allowed');
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiSearchResult(MultiSearchContainerInterface $multiSearchContainer): MultiSearchContainerInterface
    {
        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        foreach ($multiSearchContainer->getSearchContainer() as $searchContainer) {

            $query = $searchContainer->getQuery();

            $eventData = $this->eventDispatcher->dispatchAction('build_index', [
                'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
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
