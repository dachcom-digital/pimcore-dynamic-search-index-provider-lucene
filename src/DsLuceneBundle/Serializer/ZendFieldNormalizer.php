<?php

namespace DsLuceneBundle\Serializer;

use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ZendFieldNormalizer extends AbstractNormalizer
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
        if ($context['dynamic_search_context'] !== true) {
            return $data;
        }

        if (!$data instanceof \Zend_Search_Lucene_Field) {
            return $data;
        }

        $value = $this->serializer->normalize($data->getUtf8Value(), $format, $context);

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