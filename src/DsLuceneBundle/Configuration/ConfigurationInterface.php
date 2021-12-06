<?php

namespace DsLuceneBundle\Configuration;

interface ConfigurationInterface
{
    public const BUNDLE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/DsLuceneBundle';
    public const INDEX_BASE = self::BUNDLE_PATH . '/index';
    public const INDEX_BASE_GENESIS = self::INDEX_BASE . '/genesis';
    public const INDEX_BASE_STABLE = self::INDEX_BASE . '/stable';
}
