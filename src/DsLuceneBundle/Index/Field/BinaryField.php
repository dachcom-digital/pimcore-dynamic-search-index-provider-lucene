<?php

namespace DsLuceneBundle\Index\Field;

use ZendSearch\Lucene;

final class BinaryField extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        $field = Lucene\Document\Field::binary($name, $data);

        if (isset($configuration['boost']) && is_numeric($configuration['boost'])) {
            $field->boost = $configuration['boost'];
        }

        return $field;
    }
}
