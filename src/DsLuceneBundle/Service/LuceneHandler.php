<?php

namespace DsLuceneBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;

class LuceneHandler implements LuceneHandlerInterface
{
    /**
     * @var \Zend_Search_Lucene_Interface
     */
    protected $index;

    /**
     * @param \Zend_Search_Lucene_Interface $index
     */
    public function __construct(\Zend_Search_Lucene_Interface $index)
    {
        $this->index = $index;
    }

    /**
     * {@inheritDoc}
     */
    public function findTermDocuments($documentId)
    {
        $idTerm = new \Zend_Search_Lucene_Index_Term($documentId, 'id');
        return $this->index->termDocs($idTerm);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDocuments(array $documentIds)
    {
        foreach ($documentIds as $documentId) {

            try {
                $skip = $this->index->isDeleted($documentId);
            } catch (\Zend_Search_Lucene_Exception $e) {
                $skip = true;
            }

            if ($skip === true) {
                continue;
            }

            try {
                $this->index->delete($documentId);
            } catch (\Zend_Search_Lucene_Exception $e) {
                continue;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createLuceneDocument(IndexDocument $indexDocument, bool $addToIndex, $commit = true)
    {
        $doc = new \Zend_Search_Lucene_Document();
        $doc->addField(\Zend_Search_Lucene_Field::keyword('id', $indexDocument->getDocumentId()));

        if ($indexDocument->hasDocumentOption('boost')) {
            $doc->boost = $indexDocument->getDocumentOption('boost');
        }

        foreach ($indexDocument->getFields() as $field) {

            if (!$field['indexField'] instanceof \Zend_Search_Lucene_Field) {
                continue;
            }

            $doc->addField($field['indexField']);
        }

        if ($addToIndex === true) {
            $this->addDocumentToIndex($doc, $commit);
        }

        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function addDocumentToIndex(\Zend_Search_Lucene_Document $document, bool $commit = true)
    {
        $this->index->addDocument($document);

        if ($commit === true) {
            $this->index->commit();
        }
    }
}

