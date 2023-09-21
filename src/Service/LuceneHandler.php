<?php

namespace DsLuceneBundle\Service;

use DynamicSearchBundle\Document\IndexDocument;
use ZendSearch\Lucene\Document;
use ZendSearch\Lucene\Index\Term;
use ZendSearch\Lucene\SearchIndexInterface;
use ZendSearch\Exception\ExceptionInterface;

class LuceneHandler implements LuceneHandlerInterface
{
    public function __construct(protected SearchIndexInterface $index)
    {
    }

    public function findTermDocuments(int|string $documentId): array
    {
        $idTerm = new Term($documentId, 'id');

        return $this->index->termDocs($idTerm);
    }

    public function deleteDocuments(array $documentIds): void
    {
        foreach ($documentIds as $documentId) {
            try {
                $skip = $this->index->isDeleted($documentId);
            } catch (ExceptionInterface $e) {
                $skip = true;
            }

            if ($skip === true) {
                continue;
            }

            try {
                $this->index->delete($documentId);
            } catch (ExceptionInterface $e) {
                continue;
            }
        }
    }

    public function createLuceneDocument(IndexDocument $indexDocument, bool $addToIndex, $commit = true): Document
    {
        $doc = new Document();
        $doc->addField(Document\Field::keyword('id', $indexDocument->getDocumentId(), 'UTF-8'));

        if ($indexDocument->hasOptionFields()) {
            foreach ($indexDocument->getOptionFields() as $optionField) {
                if ($optionField->getName() === 'boost') {
                    $doc->boost = $optionField->getData();

                    break;
                }
            }
        }

        foreach ($indexDocument->getIndexFields() as $field) {
            if (!$field->getData() instanceof Document\Field) {
                continue;
            }

            $doc->addField($field->getData());
        }

        if ($addToIndex === true) {
            $this->addDocumentToIndex($doc, $commit);
        }

        return $doc;
    }

    public function addDocumentToIndex(Document $document, bool $commit = true): void
    {
        $this->index->addDocument($document);

        if ($commit === true) {
            $this->index->commit();
        }
    }
}
