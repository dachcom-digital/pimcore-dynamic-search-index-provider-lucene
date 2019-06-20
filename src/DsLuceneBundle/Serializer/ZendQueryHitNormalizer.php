<?php

namespace DsLuceneBundle\Serializer;

use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ZendQueryHitNormalizer extends AbstractNormalizer
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
        if (!$data instanceof \Zend_Search_Lucene_Search_QueryHit) {
            return $data;
        }

        $document = $data->getDocument();

        $context['document_index_score'] = $data->score;
        $context['document_index_id'] = $data->id;

        $value = $this->serializer->normalize($document, $format, $context);

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
        return $data instanceof \Zend_Search_Lucene_Search_QueryHit;
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
        throw new LogicException(sprintf('Cannot denormalize with "%s".', \Zend_Search_Lucene_Search_QueryHit::class));
    }
}