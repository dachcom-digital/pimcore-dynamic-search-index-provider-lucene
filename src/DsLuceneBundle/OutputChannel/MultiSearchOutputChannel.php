<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\EventDispatcher\OutputChannelModifierEventDispatcher;
use DynamicSearchBundle\OutputChannel\Context\OutputChannelContextInterface;
use DynamicSearchBundle\OutputChannel\MultiOutputChannelInterface;
use DynamicSearchBundle\OutputChannel\OutputChannelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene;

class MultiSearchOutputChannel implements OutputChannelInterface, MultiOutputChannelInterface
{
    /**
     * @var array
     */
    protected $subQueries;

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
    public static function configureOptions(OptionsResolver $optionsResolver)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
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
    public function setOutputChannelContext(OutputChannelContextInterface $outputChannelContext)
    {
        $this->outputChannelContext = $outputChannelContext;
    }

    /**
     * {@inheritdoc}
     */
    public function setEventDispatcher(OutputChannelModifierEventDispatcher $eventDispatcher)
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
     * @param mixed $query
     *
     * @throws \Exception
     */
    public function getResult($query)
    {
        throw new \Exception('not allowed');
    }

    /**
     * {@inheritdoc}
     */
    public function addSubQuery(string $subOutputChannelIdentifier, $subQuery)
    {
        $this->subQueries[$subOutputChannelIdentifier] = $subQuery;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiSearchResult(): array
    {
        $indexProviderOptions = $this->outputChannelContext->getIndexProviderOptions();

        $resultList = [];

        foreach ($this->subQueries as $subOutputChannelIdentifier => $query) {
            $eventData = $this->eventDispatcher->dispatchAction('build_index', [
                'index' => $this->storageBuilder->getLuceneIndex($indexProviderOptions, ConfigurationInterface::INDEX_BASE_STABLE)
            ]);

            /** @var Lucene\SearchIndexInterface $index */
            $index = $eventData->getParameter('index');

            $result = $index->find($query);

            $eventData = $this->eventDispatcher->dispatchAction('post_result_execute', [
                'result' => $result,
            ]);

            $resultList[$subOutputChannelIdentifier] = $eventData->getParameter('result');
        }

        return $resultList;
    }

    /**
     * {@inheritdoc}
     */
    public function getHitCount($query)
    {
        return count($query);
    }
}
