<?php

namespace DsLuceneBundle\Provider;

use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Storage\StorageBuilder;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Document\IndexDocument;
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
     * @var StorageBuilder
     */
    protected $storageBuilder;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @param StorageBuilder $storageBuilder
     */
    public function __construct(StorageBuilder $storageBuilder)
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
        $this->storageBuilder->createGenesisIndex($this->configuration['database_name'], true);
    }

    /**
     * {@inheritDoc}
     */
    public function coolDown(ContextDataInterface $contextData)
    {
        $this->storageBuilder->riseGenesisIndexToStable($this->configuration['database_name']);
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
    public function executeInsert(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
        if (!$indexDocument->hasFields()) {
            return;
        }

        $doc = new \Zend_Search_Lucene_Document();
        if ($indexDocument->hasDocumentOptions('boost')) {
            $doc->boost = $indexDocument->getDocumentOptions('boost');
        }

        $index = $this->storageBuilder->getLuceneIndex($this->configuration['database_name']);

        foreach ($indexDocument->getFields() as $field) {

            if (!$field['indexField'] instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            $doc->addField($field['indexField']);
        }

        $this->logger->debug(
            sprintf('Adding document with id %s to lucene index "%s"', $indexDocument->getUUid(), $this->configuration['database_name']),
            DsLuceneBundle::PROVIDER_NAME,
            $contextData->getName()
        );

        $index->addDocument($doc);
        $index->commit();

    }

    /**
     * {@inheritDoc}
     */
    public function executeUpdate(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function executeDelete(ContextDataInterface $contextData, IndexDocument $indexDocument)
    {
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
}