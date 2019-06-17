<?php

namespace DsLuceneBundle\OutputChannel;

use DsLuceneBundle\Storage\StorageBuilder;
use DynamicSearchBundle\Context\ContextDataInterface;
use DynamicSearchBundle\Provider\OutputChannel\SearchOutputChannelInterface;

class SearchOutputChannel implements SearchOutputChannelInterface
{
    /**
     * @var StorageBuilder
     */
    protected $storageBuilder;

    /**
     * @param StorageBuilder $storageBuilder
     */
    public function __construct(StorageBuilder $storageBuilder)
    {
        $this->storageBuilder = $storageBuilder;
    }

    public function execute(ContextDataInterface $context, array $options = [])
    {


    }

}