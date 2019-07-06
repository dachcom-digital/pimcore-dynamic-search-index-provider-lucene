<?php

namespace DsLuceneBundle\Serializer;

use DynamicSearchBundle\Document\Definition\OutputDocumentDefinitionInterface;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ZendDocumentNormalizer extends AbstractNormalizer
{
    /**
     * @param mixed $data
     * @param null  $format
     * @param array $context
     *
     * @return array
     */
    public function normalize($data, $format = null, array $context = [])
    {
        if ($context['dynamic_search_context'] !== true) {
            return $data;
        }

        if (!$data instanceof \Zend_Search_Lucene_Document) {
            return $data;
        }

        /** @var string $outputChannelName */
        $outputChannelName = $context['dynamic_search_context_options']['output_channel'];

        /** @var OutputDocumentDefinitionInterface $documentDefinition */
        $documentDefinition = $context['dynamic_search_context_options']['document_definition'];


        $values = [];
        foreach ($data->getFieldNames() as $fieldName) {

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
                $field = $data->getField($fieldName);
            } catch (\Zend_Search_Lucene_Exception $e) {
            }

            if (!$field instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            unset($context['dynamic_search_context_options']['field_definitions']);
            $context['dynamic_search_context_options']['field_definition'] = $fieldDefinition;
            $context['dynamic_search_context_options']['field_name'] = $fieldName;

            $values[$fieldName] = $this->serializer->normalize($field, $format, $context);
        }

        return $values;
    }

    /**
     * @param OutputDocumentDefinitionInterface $documentDefinition
     * @param string                            $fieldName
     *
     * @return array|null
     */
    protected function getFieldDefinition(OutputDocumentDefinitionInterface $documentDefinition, string $fieldName)
    {
        $fieldDefinitions = $documentDefinition->getOutputFieldDefinitions();


        $validFieldDefinitions = array_values(array_filter($fieldDefinitions, function ($fieldDefinition) use ($fieldName) {
            return $fieldDefinition['name'] === $fieldName;
        }));

        if (count($validFieldDefinitions) === 0) {
            return null;
        }

        $fieldDefinition = $validFieldDefinitions[0];

        return $fieldDefinition;

    }

    /**
     * @param mixed $data
     * @param null  $format
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \Zend_Search_Lucene_Document;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        throw new LogicException(sprintf('Cannot denormalize with "%s".', \Zend_Search_Lucene_Document::class));
    }
}