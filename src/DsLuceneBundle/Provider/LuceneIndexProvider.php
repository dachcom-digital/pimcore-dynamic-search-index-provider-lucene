<?php

namespace DsLuceneBundle\Provider;

use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Integrator\FieldIntegrator;
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
     * @var FieldIntegrator
     */
    protected $fieldIntegrator;

    /**
     * @param StorageBuilder  $storageBuilder
     * @param FieldIntegrator $fieldIntegrator
     */
    public function __construct(StorageBuilder $storageBuilder, FieldIntegrator $fieldIntegrator)
    {
        $this->storageBuilder = $storageBuilder;
        $this->fieldIntegrator = $fieldIntegrator;
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
        if ($indexDocument->getDocumentBoost() > 0) {
            $doc->boost = $indexDocument->getDocumentBoost();
        }

        $options = $contextData->getIndexProviderOptions();

        $index = $this->storageBuilder->getLuceneIndex($options['database_name']);

        foreach ($indexDocument->getFields() as $field) {
            $this->fieldIntegrator->integrate($field, $doc);
        }

        $this->logger->debug(sprintf('Adding document with id %s to lucene index "%s"', $indexDocument->getUUid(), $options['database_name']),
            DsLuceneBundle::PROVIDER_NAME, $contextData->getName());

        $doc->addField(\Zend_Search_Lucene_Field::Keyword('uid', $indexDocument->getUUid(), 'UTF-8'));

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