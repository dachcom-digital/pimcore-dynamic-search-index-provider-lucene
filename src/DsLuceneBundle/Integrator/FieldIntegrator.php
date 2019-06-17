<?php

namespace DsLuceneBundle\Integrator;

use DynamicSearchBundle\Transformer\Field\Type\BinaryType;
use DynamicSearchBundle\Transformer\Field\Type\KeywordType;
use DynamicSearchBundle\Transformer\Field\Type\StringType;
use DynamicSearchBundle\Transformer\Field\Type\TextType;
use DynamicSearchBundle\Transformer\Field\Type\TypeInterface;
use PHPStan\Type\BooleanType;

final class FieldIntegrator
{
    const UTF8 = 'UTF-8';

    public function integrate(TypeInterface $type, \Zend_Search_Lucene_Document $document)
    {
        $field = null;

        if ($type->getIndexed() === false) {
            $this->generateUnIndexedField($type);
            $document->addField($field);
            return;
        }

        if ($type->getStored() === false) {
            $this->generateUnStoredField($type);
            $document->addField($field);
            return;
        }

        switch (get_class($type)) {
            case KeywordType::class:
                $field = $this->generateKeywordField($type);
                break;
            case StringType::class:
            case TextType::class:
            case BooleanType::class:
                $field = $this->generateTextField($type);
                break;
            case BinaryType::class:
                $field = $this->generateBinaryField($type);
                break;
        }

        if ($field instanceof \Zend_Search_Lucene_Field) {
            $document->addField($field);
        }
    }

    protected function generateKeywordField(TypeInterface $type)
    {
        $field = \Zend_Search_Lucene_Field::keyword($type->getName(), $type->getValue(), self::UTF8);
        $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }

    protected function generateTextField(TypeInterface $type)
    {
        $field = \Zend_Search_Lucene_Field::text($type->getName(), $type->getValue(), self::UTF8);
        $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }

    protected function generateUnStoredField(TypeInterface $type)
    {
        $field = \Zend_Search_Lucene_Field::unStored($type->getName(), $type->getValue(), self::UTF8);
        $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }

    protected function generateUnIndexedField(TypeInterface $type)
    {
        $field = \Zend_Search_Lucene_Field::unIndexed($type->getName(), $type->getValue(), self::UTF8);
        $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }

    protected function generateBinaryField(TypeInterface $type)
    {
        $field = \Zend_Search_Lucene_Field::binary($type->getName(), $type->getValue());
        $field->boost = $type->getBoost() > 1 ? $type->getBoost() : 1.0;

        return $field;
    }
}
