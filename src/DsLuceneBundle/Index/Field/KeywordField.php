<?php

namespace DsLuceneBundle\Index\Field;

use DynamicSearchBundle\Transformer\Container\FieldContainerInterface;

final class KeywordField extends AbstractType
{
    /**
     * {@inheritDoc}
     */
    public function build(FieldContainerInterface $fieldContainer)
    {
        $field = \Zend_Search_Lucene_Field::keyword($fieldContainer->getName(), $fieldContainer->getData(), self::UTF8);
       // $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
