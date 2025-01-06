<?php

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace DsLuceneBundle\Normalizer\Helper;

use ZendSearch\Exception\ExceptionInterface;
use ZendSearch\Lucene\Document\Field;
use ZendSearch\Lucene\Search\QueryHit;

class LuceneDocumentDataExtractor
{
    public function extract(QueryHit $hit): array
    {
        $document = $hit->getDocument();

        $data = [];
        foreach ($document->getFieldNames() as $fieldName) {
            $field = null;
            $fieldDefinition = null;

            try {
                $field = $document->getField($fieldName);
            } catch (ExceptionInterface $e) {
                // fail silently
            }

            if (!$field instanceof Field) {
                continue;
            }

            $data[$fieldName] = $field->getUtf8Value();
        }

        return $data;
    }
}
