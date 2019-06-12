<?php

namespace DsLuceneBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class DsLuceneBundle extends AbstractPimcoreBundle
{
    const PROVIDER_NAME = 'lucene';

    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}

