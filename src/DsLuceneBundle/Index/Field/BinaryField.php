<?php

namespace DsLuceneBundle\Index\Field;

use DynamicSearchBundle\Transformer\Container\FieldContainerInterface;

final class BinaryField extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function build(FieldContainerInterface $fieldContainer)
    {
        $field = \Zend_Search_Lucene_Field::binary($fieldContainer->getName(), $fieldContainer->getData());
       // $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
