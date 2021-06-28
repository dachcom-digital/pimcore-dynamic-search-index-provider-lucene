<?php

namespace DsLuceneBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('ds_lucene');
        $rootNode = $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
