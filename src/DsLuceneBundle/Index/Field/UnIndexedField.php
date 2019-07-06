<?php

namespace DsLuceneBundle\Index\Field;

final class UnIndexedField extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        $field = \Zend_Search_Lucene_Field::unIndexed($name, $data, self::UTF8);
        // $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
