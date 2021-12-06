<?php

namespace DsLuceneBundle\Provider;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Exception\LuceneException;
use DsLuceneBundle\Service\LuceneHandler;
use DsLuceneBundle\Service\LuceneStorageBuilder;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Document\IndexDocument;
use DynamicSearchBundle\Exception\ProviderException;
use DynamicSearchBundle\Logger\LoggerInterface;
use DynamicSearchBundle\Provider\IndexProviderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene\SearchIndexInterface;

class LuceneIndexProvider implements IndexProviderInterface
{
    protected array $options;
    protected LoggerInterface $logger;
    protected LuceneStorageBuilder $storageBuilder;

    public function __construct(
        LoggerInterface $logger,
        LuceneStorageBuilder $storageBuilder
    ) {
        $this->logger = $logger;
        $this->storageBuilder = $storageBuilder;
    }

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $defaults = [
            'database_name'         => null,
            'force_adding_document' => true,
            'analyzer'              => [],
        ];

        $resolver->setDefaults($defaults);
        $resolver->setRequired(array_keys($defaults));
        $resolver->setAllowedTypes('database_name', ['string']);
        $resolver->setAllowedTypes('analyzer', ['array']);
        $resolver->setAllowedTypes('force_adding_document', ['bool']);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function warmUp(ContextDefinitionInterface $contextDefinition): void
    {
        if ($contextDefinition->getContextDispatchType() !== ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX) {
            return;
        }

        try {
            $this->storageBuilder->createGenesisIndex($this->options, true);
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    public function coolDown(ContextDefinitionInterface $contextDefinition): void
    {
        if ($contextDefinition->getContextDispatchType() !== ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX) {
            try {
                $this->storageBuilder->optimizeLuceneIndex($this->options['database_name'], ConfigurationInterface::INDEX_BASE_STABLE);
            } catch (\Throwable $e) {
                return;
            }

            return;
        }

        try {
            $this->storageBuilder->riseGenesisIndexToStable($this->options);
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    public function cancelledShutdown(ContextDefinitionInterface $contextDefinition): void
    {
    }

    public function emergencyShutdown(ContextDefinitionInterface $contextDefinition): void
    {
    }

    public function processDocument(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        try {
            switch ($contextDefinition->getContextDispatchType()) {
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INDEX:
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_INSERT:
                    $this->executeIndex($contextDefinition, $indexDocument);

                    break;
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_UPDATE:
                    $this->executeUpdate($contextDefinition, $indexDocument);

                    break;
                case ContextDefinitionInterface::CONTEXT_DISPATCH_TYPE_DELETE:
                    $this->executeDelete($contextDefinition, $indexDocument);

                    break;
                default:
                    throw new \Exception(sprintf('invalid context dispatch type "%s". cannot perform index provider dispatch.',
                        $contextDefinition->getContextDispatchType()));
            }
        } catch (\Throwable $e) {
            throw new ProviderException($e->getMessage(), DsLuceneBundle::PROVIDER_NAME, $e);
        }
    }

    /**
     * @throws LuceneException
     */
    protected function executeIndex(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$indexDocument->hasIndexFields()) {
            return;
        }

        $index = $this->getGenesisIndex($this->getLocaleFromIndexDocumentResource($indexDocument));

        $luceneHandler = new LuceneHandler($index);
        $luceneHandler->createLuceneDocument($indexDocument, true, false);

        $this->logger->debug(
            sprintf('Adding document with id %s to lucene index "%s"', $indexDocument->getDocumentId(), $this->options['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    /**
     * @throws LuceneException
     */
    protected function executeInsert(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->storageBuilder->indexExists($this->options['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->options['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $index = $this->getStableIndex($this->getLocaleFromIndexDocumentResource($indexDocument));

        $luceneHandler = new LuceneHandler($index);
        $luceneHandler->createLuceneDocument($indexDocument, true, true);

        $this->logger->debug(
            sprintf('Adding document with id %s to stable lucene index "%s"', $indexDocument->getDocumentId(), $this->options['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    /**
     * @throws LuceneException
     */
    protected function executeUpdate(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->storageBuilder->indexExists($this->options['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->options['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $luceneHandler = new LuceneHandler($this->getStableIndex($this->getLocaleFromIndexDocumentResource($indexDocument)));
        $termDocuments = $luceneHandler->findTermDocuments($indexDocument->getDocumentId());

        if (!is_array($termDocuments) || count($termDocuments) === 0) {
            $createNewDocumentMessage = $this->options['force_adding_document'] === true
                ? ' Going to add new document (options "force_adding_document" is set to "true")'
                : ' Going to skip adding new document (options "force_adding_document" is set to "false")';
            $this->logger->debug(
                sprintf('document with id "%s" not found. %s', $indexDocument->getDocumentId(), $createNewDocumentMessage),
                DsLuceneBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            $this->executeInsert($contextDefinition, $indexDocument);

            return;
        }

        $luceneHandler->deleteDocuments($termDocuments);
        $luceneHandler->createLuceneDocument($indexDocument, true, true);

        $this->logger->debug(
            sprintf('Updating document with id %s to stable lucene index "%s"', $indexDocument->getDocumentId(), $this->options['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextDefinition->getName()
        );
    }

    /**
     * @throws LuceneException
     */
    protected function executeDelete(ContextDefinitionInterface $contextDefinition, IndexDocument $indexDocument): void
    {
        if (!$this->storageBuilder->indexExists($this->options['database_name'], ConfigurationInterface::INDEX_BASE_STABLE)) {
            $this->logger->error(
                sprintf('could not update index. index with name "%s" is not available in a stable state', $this->options['database_name']),
                DsLuceneBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $luceneHandler = new LuceneHandler($this->getStableIndex());
        $termDocuments = $luceneHandler->findTermDocuments($indexDocument->getDocumentId());

        if (!is_array($termDocuments) || count($termDocuments) === 0) {
            $this->logger->error(
                sprintf('document with id "%s" could not be found. Skipping deletion...', $indexDocument->getDocumentId()),
                DsLuceneBundle::PROVIDER_NAME,
                $contextDefinition->getName()
            );

            return;
        }

        $luceneHandler->deleteDocuments($termDocuments);
    }

    /**
     * @throws LuceneException
     */
    protected function getStableIndex(?string $locale = null): SearchIndexInterface
    {
        return $this->storageBuilder->getLuceneIndex($this->options, ConfigurationInterface::INDEX_BASE_STABLE, $locale, true);
    }

    /**
     * @throws LuceneException
     */
    protected function getGenesisIndex(?string $locale = null): SearchIndexInterface
    {
        return $this->storageBuilder->getLuceneIndex($this->options, ConfigurationInterface::INDEX_BASE_GENESIS, $locale, true);
    }

    protected function getLocaleFromIndexDocumentResource(IndexDocument $indexDocument): ?string
    {
        $locale = null;
        $normalizerOptions = $indexDocument->getResourceMeta()->getNormalizerOptions();
        if (isset($normalizerOptions['locale']) && !empty($normalizerOptions['locale'])) {
            $locale = $normalizerOptions['locale'];
        }

        return $locale;
    }
}
