<?php

namespace DsLuceneBundle\Configuration;

interface ConfigurationInterface
{
    const BUNDLE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/DsLuceneBundle';

    const INDEX_BASE = self::BUNDLE_PATH . '/index';

    const INDEX_BASE_GENESIS = self::INDEX_BASE . '/genesis';

    const INDEX_BASE_STABLE = self::INDEX_BASE . '/stable';

}