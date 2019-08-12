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
        // $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
