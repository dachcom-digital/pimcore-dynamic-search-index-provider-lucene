<?php

namespace DsLuceneBundle\Normalizer;

use DsLuceneBundle\Normalizer\Helper\LuceneDocumentDataExtractor;
use DynamicSearchBundle\Context\ContextDefinitionInterface;
use DynamicSearchBundle\Exception\NormalizerException;
use DynamicSearchBundle\Normalizer\DocumentNormalizerInterface;
use DynamicSearchBundle\OutputChannel\Query\Result\RawResultInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ZendSearch\Lucene\Search\QueryHit;

class DocumentKeyValueNormalizer implements DocumentNormalizerInterface
{
    protected array $options;

    public static function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['skip_fields']);
        $resolver->setAllowedTypes('skip_fields', ['string[]']);
        $resolver->setDefaults([
            'skip_fields' => []
        ]);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptions(array $params = []): array
    {
        return $this->options;
    }

    public function normalize(RawResultInterface $rawResult, ContextDefinitionInterface $contextDefinition, string $outputChannelName): array
    {
        $data = $rawResult->getData();

        if (!is_array($data)) {
            $message = sprintf('Data needs to be type of "array", "%s" given', is_object($data) ? get_class($data) : gettype($data));
            throw new NormalizerException($message, __CLASS__);
        }

        $normalizedDocuments = [];
        $dataExtractor = new LuceneDocumentDataExtractor();

        foreach ($data as $documentHit) {
            if ($documentHit instanceof QueryHit) {
                $documentData = $dataExtractor->extract($documentHit);
                // remove blacklist keys (from skip_fields option)
                $normalizedDocuments[] = array_diff_key($documentData, array_flip($this->options['skip_fields']));
            }
        }

        return $normalizedDocuments;
    }
}
