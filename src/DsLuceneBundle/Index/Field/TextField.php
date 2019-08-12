<?php

namespace DsLuceneBundle\Index\Field;

use ZendSearch\Lucene;

final class TextField extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function build(string $name, $data, array $configuration = [])
    {
        $field = Lucene\Document\Field::text($name, $data, self::UTF8);

        if (isset($configuration['boost']) && is_numeric($configuration['boost'])) {
            $field->boost = $configuration['boost'];
        }

        return $field;
    }
}
