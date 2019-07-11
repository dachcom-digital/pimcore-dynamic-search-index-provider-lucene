<?php

namespace DsLuceneBundle\Normalizer\Helper;

class LuceneDocumentDataExtractor
{
    /**
     * @param \Zend_Search_Lucene_Search_QueryHit $hit
     *
     * @return array
     */
    public function extract(\Zend_Search_Lucene_Search_QueryHit $hit)
    {
        $document = $hit->getDocument();

        $data = [];
        foreach ($document->getFieldNames() as $fieldName) {

            $field = null;
            $fieldDefinition = null;

            try {
                $field = $document->getField($fieldName);
            } catch (\Zend_Search_Lucene_Exception $e) {
                // fail silently
            }

            if (!$field instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            $data[$fieldName] = $field->getUtf8Value();
        }

        return $data;

    }

}