<?php

namespace DsLuceneBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;
use ZendSearch\Lucene\Document;

interface LuceneHandlerInterface
{
    public function findTermDocuments(int|string $documentId): array;

    public function deleteDocuments(array $documentIds): void;

    public function createLuceneDocument(IndexDocument $indexDocument, bool $addToIndex, bool $commit = true): Document;

    public function addDocumentToIndex(Document $document, bool $commit = true): void;
}
