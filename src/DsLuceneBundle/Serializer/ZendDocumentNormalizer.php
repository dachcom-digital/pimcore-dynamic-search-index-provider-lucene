<?php

namespace DsLuceneBundle\Serializer;

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
        if (!$data instanceof \Zend_Search_Lucene_Document) {
            return $data;
        }

        $outputChannelName = $context['output_channel'];
        $fieldDefinitions = $context['field_definitions'];

        $values = [];
        foreach ($data->getFieldNames() as $fieldName) {

            $field = null;

            // skip field if not required in current output channel
            if (isset($fieldDefinitions[$fieldName]) && $fieldDefinitions[$fieldName]['visibility'][$outputChannelName] === false) {
                continue;
            }

            try {
                $field = $data->getField($fieldName);
            } catch (\Zend_Search_Lucene_Exception $e) {
            }

            if (!$field instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            unset($context['field_definitions']);
            $context['field_definition'] = $fieldDefinitions[$fieldName];

            $values[$fieldName] = $this->serializer->normalize($field, $format, $context);
        }

        return $values;
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