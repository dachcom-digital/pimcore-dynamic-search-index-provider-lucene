<?php

namespace DsLuceneBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;
use ZendSearch\Lucene\Document;

interface LuceneHandlerInterface
{
    /**
     * @param string $documentId
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
     * @return Document
     */
    public function createLuceneDocument(IndexDocument $indexDocument, bool $addToIndex, $commit = true);

    /**
     * @param Document $document
     * @param bool     $commit
     */
    public function addDocumentToIndex(Document $document, bool $commit = true);
}
