<?php

namespace DsLuceneBundle\Index\Field;

final class BinaryField extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        $field = \Zend_Search_Lucene_Field::binary($name, $data);
       // $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
