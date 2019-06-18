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
     * @param StorageBuilder  $storageBuilder
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
    public function warmUp(ContextDataInterface $contextData)
    {
        $options = $contextData->getIndexProviderOptions();

        $this->storageBuilder->createGenesisIndex($options['database_name'], true);

    }

    /**
     * {@inheritDoc}
     */
    public function coolDown(ContextDataInterface $contextData)
    {
        $options = $contextData->getIndexProviderOptions();

        $this->storageBuilder->riseGenesisIndexToStable($options['database_name']);
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

        $indexProviderOptions = $contextData->getIndexProviderOptions();
        $index = $this->storageBuilder->getLuceneIndex($indexProviderOptions['database_name']);

        foreach ($indexDocument->getFields() as $field) {

            if (!$field['indexField'] instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            $doc->addField($field['indexField']);
        }

        $this->logger->debug(
            sprintf('Adding document with id %s to lucene index "%s"', $indexDocument->getUUid(), $indexProviderOptions['database_name']),
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
            'database_name'               => null,
            'output_channel_autocomplete' => 'lucene',
            'output_channel_search'       => 'lucene'
        ];

        $resolver->setDefaults($defaults);
        $resolver->setRequired(array_keys($defaults));

        $resolver->setAllowedTypes('database_name', ['string']);
    }
}