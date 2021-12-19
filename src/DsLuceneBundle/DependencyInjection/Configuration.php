<?php

namespace DsLuceneBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ds_lucene');

        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('index')
                    ->addDefaultsIfNotSet()
                        ->children()
                        ->booleanNode('base_path')->defaultValue('%kernel.project_dir%/var/bundles/DsLuceneBundle/index')->end()
                    ->end()
                ->end()
            ->end();

         return $treeBuilder;
    }
}
