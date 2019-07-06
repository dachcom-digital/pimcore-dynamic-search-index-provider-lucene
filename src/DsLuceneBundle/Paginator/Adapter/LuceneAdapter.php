<?php

namespace DsLuceneBundle\Paginator\Adapter;

use DynamicSearchBundle\Document\Definition\OutputDocumentDefinitionInterface;
use DynamicSearchBundle\OutputChannel\Result\Document\Document;
use DynamicSearchBundle\Paginator\AdapterInterface;
use Symfony\Component\Serializer\SerializerInterface;

class LuceneAdapter implements AdapterInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var string
     */
    protected $contextName;

    /**
     * @var string
     */
    protected $outputChannelName;

    /**
     * @var OutputDocumentDefinitionInterface
     */
    protected $outputDocumentDefinition;

    /**
     * array|\Zend_Search_Lucene_Search_QueryHit[]
     *
     * @var array
     */
    protected $array = null;

    /**
     * Item count
     *
     * @var int
     */
    protected $count = null;

    /**
     * @param array $data
     */
    public function __construct($data)
    {
        $this->array = $data;
        $this->count = count($this->array);
    }

    /**
     * {@inheritDoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * {@inheritDoc}
     */
    public function setContextName(string $contextName)
    {
        $this->contextName = $contextName;
    }

    /**
     * {@inheritDoc}
     */
    public function setOutputChannelName(string $outputChannelName)
    {
        $this->outputChannelName = $outputChannelName;
    }

    /**
     * {@inheritDoc}
     */
    public function setOutputDocumentDefinition(OutputDocumentDefinitionInterface $outputDocumentDefinition)
    {
        $this->outputDocumentDefinition = $outputDocumentDefinition;
    }

    /**
     * @param int $offset           Page offset
     * @param int $itemCountPerPage Number of items per page
     *
     * @return array
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $data = array_map(function ($document) {
            return new Document($document, $this->contextName, $this->outputChannelName, $this->outputDocumentDefinition);
        }, array_slice($this->array, $offset, $itemCountPerPage));

        return $this->serializer->normalize($data);
    }

    /**
     * Returns the total number of rows in the array.
     *
     * @return int
     */
    public function count()
    {
        return $this->count;
    }
}
