<?php

namespace DsLuceneBundle\Normalizer;

use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Document\Definition\DocumentDefinition;
use DynamicSearchBundle\Document\Definition\DocumentDefinitionBuilderInterface;
use DynamicSearchBundle\Document\Definition\DocumentDefinitionInterface;
use DynamicSearchBundle\Manager\DataManagerInterface;
use DynamicSearchBundle\Manager\TransformerManagerInterface;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\Normalizer\Resource\ResourceMeta;
use DynamicSearchBundle\OutputChannel\Result\Document\Document;
use DynamicSearchBundle\OutputChannel\Result\Document\DocumentInterface;
use DynamicSearchBundle\Manager\DocumentDefinitionManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\SerializerInterface;

class DocumentNormalizer implements DocumentNormalizerInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var TransformerManagerInterface
     */
    protected $transformerManager;

    /**
     * @var DataManagerInterface
     */
    protected $dataManager;

    /**
     * @var DocumentDefinitionManagerInterface
     */
    protected $documentDefinitionManager;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param SerializerInterface                $serializer
     * @param TransformerManagerInterface        $transformerManager
     * @param DataManagerInterface               $dataManager
     * @param DocumentDefinitionManagerInterface $documentDefinitionManager
     */
    public function __construct(
        SerializerInterface $serializer,
        TransformerManagerInterface $transformerManager,
        DataManagerInterface $dataManager,
        DocumentDefinitionManagerInterface $documentDefinitionManager
    ) {
        $this->serializer = $serializer;
        $this->transformerManager = $transformerManager;
        $this->dataManager = $dataManager;
        $this->documentDefinitionManager = $documentDefinitionManager;
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function normalize(ContextDataInterface $contextData, string $outputChannelName, $data)
    {
        $documentDefinitionBuilder = $this->documentDefinitionManager->getDocumentDefinitionBuilder($contextData);

        $normalizedData = [];

        foreach ($data as $hit) {

            if (!$hit instanceof \Zend_Search_Lucene_Search_QueryHit) {
                $normalizedData[] = $hit;
                continue;
            }

            $normalizedDocument = $this->normalizeHit($hit, $contextData->getName(), $outputChannelName, $documentDefinitionBuilder);
            if ($normalizedDocument instanceof DocumentInterface) {
                $normalizedData[] = $normalizedDocument;
            }
        }

        return $this->serializer->normalize($normalizedData);
    }

    /**
     * @param \Zend_Search_Lucene_Search_QueryHit $hit
     * @param string                              $contextName
     * @param string                              $outputChannelName
     * @param DocumentDefinitionBuilderInterface  $documentDefinitionBuilder
     *
     * @return Document
     * @throws \Exception
     */
    protected function normalizeHit(
        \Zend_Search_Lucene_Search_QueryHit $hit,
        string $contextName,
        string $outputChannelName,
        DocumentDefinitionBuilderInterface $documentDefinitionBuilder
    ) {
        $document = $hit->getDocument();

        $systemColumns = ['_resource_id', '_resource_collection_type', '_resource_type'];

        try {
            $documentMeta = new ResourceMeta(
                $document->getField('id')->getUtf8Value(),
                $document->getField('_resource_id')->getUtf8Value(),
                $document->getField('_resource_collection_type')->getUtf8Value(),
                $document->getField('_resource_type')->getUtf8Value()
            );

        } catch (\Zend_Search_Lucene_Exception $e) {
            throw new \Exception(sprintf('cannot create resource meta for document normalizer. Error was: %s', $e->getMessage()));
        }

        $documentDefinition = new DocumentDefinition($documentMeta);
        $documentDefinitionBuilder->buildDefinition($documentDefinition);

        $data = [];
        foreach ($document->getFieldNames() as $fieldName) {

            if (in_array($fieldName, $systemColumns)) {
                continue;
            }

            $field = null;
            $fieldDefinition = $this->getFieldDefinition($documentDefinition, $fieldName);

            if ($fieldDefinition !== null) {
                $visibility = isset($fieldDefinition['channel_visibility']) ? $fieldDefinition['channel_visibility'] : null;
                // skip field if not required in current output channel
                if (!is_null($visibility) && $visibility[$outputChannelName] === false) {
                    continue;
                }
            }

            try {
                $field = $document->getField($fieldName);
            } catch (\Zend_Search_Lucene_Exception $e) {
            }

            if (!$field instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            $data[$fieldName] = $field->getUtf8Value();
        }

        return new Document($data, $contextName, $outputChannelName);

    }

    /**
     * @param DocumentDefinitionInterface $documentDefinition
     * @param string                      $fieldName
     *
     * @return array|null
     */
    protected function getFieldDefinition(DocumentDefinitionInterface $documentDefinition, string $fieldName)
    {
        $fieldDefinitions = $documentDefinition->getDocumentFieldDefinitions();

        $validFieldDefinitions = array_values(array_filter($fieldDefinitions, function ($fieldDefinition) use ($fieldName) {
            return $fieldDefinition['name'] === $fieldName;
        }));

        if (count($validFieldDefinitions) === 0) {
            return null;
        }

        $fieldDefinition = $validFieldDefinitions[0]['output_transformer'];

        return $fieldDefinition;

    }
}