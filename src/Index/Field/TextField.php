<?php

namespace DsLuceneBundle\Index\Field;

use ZendSearch\Lucene;

final class TextField extends AbstractType
{
    public function build(string $name, mixed $data, array $configuration = []): Lucene\Document\Field
    {
        $field = Lucene\Document\Field::text($name, $data, self::UTF8);

        if (isset($configuration['boost']) && is_numeric($configuration['boost'])) {
            $field->boost = $configuration['boost'];
        }

        return $field;
    }
}
