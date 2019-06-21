<?php

namespace DsLuceneBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;

interface LuceneHandlerInterface
{
    /**
     * @param $documentId
     *
     * @return array
     */
    public function findTermDocuments($documentId);

    /**
     * @param array $documentIds
     */
    public function deleteDocuments(array $documentIds);

    /**
     * @param IndexDocument $indexDocument
     * @param bool          $addToIndex
     * @param bool          $commit
     *
     * @return \Zend_Search_Lucene_Document
     */
    public function createLuceneDocument(IndexDocument $indexDocument, bool $addToIndex, $commit = true);

    /**
     * @param \Zend_Search_Lucene_Document $document
     * @param bool                         $commit
     */
    public function addDocumentToIndex(\Zend_Search_Lucene_Document $document, bool $commit = true);

}

