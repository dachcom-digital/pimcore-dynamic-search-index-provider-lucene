<?php

namespace DsLuceneBundle\Provider;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Exception\LuceneException;
use DsLuceneBundle\Service\LuceneHandler;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Document\IndexDocument;
use DynamicSearchBundle\Exception\ProviderException;
use DynamicSearchBundle\Logger\LoggerInterface;
use DynamicSearchBundle\Provider\IndexProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LuceneIndexProvider implements IndexProviderInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var LuceneStorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var array
     */
    protected $configuration;

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
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritDoc}
     */
    public function warmUp(ContextDataInterface $contextData)
    {
        if ($contextData->getContextDispatchType() !== ContextDataInterface::CONTEXT_DISPATCH_TYPE_INDEX) {
            return;
        }

        try {
            $this->storageBuilder->createGenesisIndex($this->configuration['database_name'], true);
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function coolDown(ContextDataInterface $contextData)
    {
        if ($contextData->getContextDispatchType() !== ContextDataInterface::CONTEXT_DISPATCH_TYPE_INDEX) {

            try {
                $this->storageBuilder->optimizeLuceneIndex($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_STABLE);
            } catch (\Throwable $e) {
                return;
            }

            return;
        }

        try {
            $this->storageBuilder->riseGenesisIndexToStable($this->configuration['database_name']);
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function cancelledShutdown(ContextDataInterface $contextData)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function emergencyShutdown(ContextDataInterface $contextData)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function execute(ContextDataInterface $contextData)
    {
        $runtimeValues = $this->validateRuntimeValues($contextData->getRuntimeValues());

        $indexDocument = $runtimeValues['index_document'];

        try {
            switch ($contextData->getContextDispatchType()) {
                case ContextDataInterface::CONTEXT_DISPATCH_TYPE_INDEX:
                    $this->executeIndex($contextData, $indexDocument);
                    break;
                case ContextDataInterface::CONTEXT_DISPATCH_TYPE_INSERT:
                    $this->executeInsert($contextData, $indexDocument);
                    break;
                case ContextDataInterface::CONTEXT_DISPATCH_TYPE_UPDATE:
                    $this->executeUpdate($contextData, $indexDocument);
                    break;
                case ContextDataInterface::CONTEXT_DISPATCH_TYPE_DELETE:
                    $this->executeDelete($contextData, $indexDocument);
                    break;
                default:
                    throw new \Exception(sprintf('invalid context dispatch type "%s". cannot perform index provider dispatch.', $contextData->getContextDispatchType()));
            }
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    /**
     * @param ContextDataInterface $contextData
     * @param IndexDocument        $indexDocument
     *
     * @throws LuceneException
     */
    protected function executeIndex(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
        if (!$indexDocument->hasIndexFields()) {
            return;
        }

        $index = $this->getGenesisIndex();

        $luceneHandler = new LuceneHandler($index);
        $luceneHandler->createLuceneDocument($indexDocument, true, false);

        $this->logger->debug(
            sprintf('Adding document with id %s to lucene index "%s"', $indexDocument->getDocumentId(), $this->configuration['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextData->getName()
        );
    }

    /**
     * @param ContextDataInterface $contextData
     * @param IndexDocument        $indexDocument
     *
     * @throws LuceneException
     */
    protected function executeInsert(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
        if (!$this->storageBuilder->indexExists($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->configuration['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextData->getName()
            );
            return;
        }

        $index = $this->getStableIndex();

        $luceneHandler = new LuceneHandler($index);
        $luceneHandler->createLuceneDocument($indexDocument, true, true);

        $this->logger->debug(
            sprintf('Adding document with id %s to stable lucene index "%s"', $indexDocument->getDocumentId(), $this->configuration['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextData->getName()
        );
    }

    /**
     * @param ContextDataInterface $contextData
     * @param IndexDocument        $indexDocument
     *
     * @throws LuceneException
     */
    protected function executeUpdate(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
        $runtimeValues = $this->validateRuntimeValues($contextData->getRuntimeValues());

        if (!$this->storageBuilder->indexExists($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->configuration['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextData->getName()
            );
            return;
        }

        $luceneHandler = new LuceneHandler($this->getStableIndex());
        $termDocuments = $luceneHandler->findTermDocuments($indexDocument->getDocumentId());

        if (!is_array($termDocuments) || count($termDocuments) === 0) {

            $createNewDocumentMessage = $runtimeValues['force_adding'] == true
                ? ' Going to add new document (runtime options "force_adding" is set to "true")'
                : ' Going to skip adding new document (runtime options "force_adding" is set to "false")';
            $this->logger->debug(
                sprintf('document with id "%s" not found. %s', $indexDocument->getDocumentId(), $createNewDocumentMessage),
                DsLuceneBundle::PROVIDER_NAME,
                $contextData->getName()
            );

            $this->executeInsert($contextData, $indexDocument);

            return;
        }

        $luceneHandler->deleteDocuments($termDocuments);
        $luceneHandler->createLuceneDocument($indexDocument, true, true);

        $this->logger->debug(
            sprintf('Updating document with id %s to stable lucene index "%s"', $indexDocument->getDocumentId(), $this->configuration['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextData->getName()
        );
    }

    /**
     * @param ContextDataInterface $contextData
     * @param IndexDocument        $indexDocument
     *
     * @throws LuceneException
     */
    protected function executeDelete(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
        $runtimeValues = $this->validateRuntimeValues($contextData->getRuntimeValues());

        if (!$this->storageBuilder->indexExists($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->configuration['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextData->getName()
            );
            return;
        }

        $luceneHandler = new LuceneHandler($this->getStableIndex());
        $termDocuments = $luceneHandler->findTermDocuments($indexDocument->getDocumentId());

        if (!is_array($termDocuments) || count($termDocuments) === 0) {
            $this->logger->error(
                sprintf('document with id "%s" could not be found. Skipping deletion...', $indexDocument->getDocumentId()),
                DsLuceneBundle::PROVIDER_NAME,
                $contextData->getName()
            );

            return;
        }

        $luceneHandler->deleteDocuments($termDocuments);

    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $defaults = [
            'database_name' => null
        ];

        $resolver->setDefaults($defaults);
        $resolver->setRequired(array_keys($defaults));

        $resolver->setAllowedTypes('database_name', ['string']);
    }

    /**
     * @param array $runtimeValues
     *
     * @return array
     */
    protected function validateRuntimeValues(array $runtimeValues = [])
    {
        if (!isset($runtimeValues['force_adding'])) {
            $runtimeValues['force_adding'] = true;
        }

        if (!isset($runtimeValues['index_document'])) {
            $runtimeValues['index_document'] = null;
        }

        return $runtimeValues;
    }

    /**
     * @return \Zend_Search_Lucene_Interface
     * @throws LuceneException
     */
    protected function getStableIndex()
    {
        return $this->storageBuilder->getLuceneIndex($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_STABLE);
    }

    /**
     * @return \Zend_Search_Lucene_Interface
     * @throws LuceneException
     */
    protected function getGenesisIndex()
    {
        return $this->storageBuilder->getLuceneIndex($this->configuration['database_name'], ConfigurationInterface::INDEX_BASE_GENESIS);
    }
}