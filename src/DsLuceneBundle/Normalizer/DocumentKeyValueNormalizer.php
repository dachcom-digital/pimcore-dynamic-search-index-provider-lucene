<?php

namespace DsLuceneBundle\Normalizer;

use DsLuceneBundle\Normalizer\Helper\LuceneDocumentDataExtractor;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentKeyValueNormalizer implements DocumentNormalizerInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['skip_fields']);
        $resolver->setAllowedTypes('skip_fields', ['string[]']);
        $resolver->setDefaults([
            'skip_fields' => []
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(ContextDataInterface $contextData, string $outputChannelName, $data)
    {
        $normalizedDocuments = [];

        $dataExtractor = new LuceneDocumentDataExtractor();

        foreach ($data as $documentHit) {
            if ($documentHit instanceof \Zend_Search_Lucene_Search_QueryHit) {
                $documentData = $dataExtractor->extract($documentHit);
                // remove blacklist keys (from skip_fields option)
                $normalizedDocuments[] = array_diff_key($documentData, array_flip($this->options['skip_fields']));
            }
        }

        return $normalizedDocuments;
    }
}