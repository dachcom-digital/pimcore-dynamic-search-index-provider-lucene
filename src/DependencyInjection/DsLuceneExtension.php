<?php

namespace DsLuceneBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

class DsLuceneExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('dynamic_search.ds_lucene.index.base_path', $config['index']['base_path']);

        $loader = new YamlFileLoader($container, new FileLocator([__DIR__ . '/../../config']));
        $loader->load('services.yaml');
    }
}
