<?php

namespace DsLuceneBundle\Paginator\Adapter;

use DynamicSearchBundle\Paginator\AdapterInterface;
use Symfony\Component\Serializer\SerializerInterface;

class LuceneAdapter implements AdapterInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

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

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param int $offset           Page offset
     * @param int $itemCountPerPage Number of items per page
     *
     * @return array
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $data = array_slice($this->array, $offset, $itemCountPerPage);

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
