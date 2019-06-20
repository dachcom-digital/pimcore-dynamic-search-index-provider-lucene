<?php

namespace DsLuceneBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ZendFieldNormalizer implements NormalizerInterface
{
    /**
     * @param mixed $data
     * @param null  $format
     * @param array $context
     *
     * @return mixed
     */
    public function normalize($data, $format = null, array $context = [])
    {
        if (!$data instanceof \Zend_Search_Lucene_Field) {
            return $data;
        }

        $fieldDefinition = $context['field_definition'];

        // add output_transformer here!
        $value = $data->getUtf8Value();

        return $value;

    }

    /**
     * @param mixed $data
     * @param null  $format
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof \Zend_Search_Lucene_Field;
    }
}