<?php

namespace DsLuceneBundle\Storage;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use Symfony\Component\Filesystem\Filesystem;

class StorageBuilder
{
    protected $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function createGenesisIndex(string $databaseName, $killExistingInstance = false)
    {
        if ($this->indexExists($databaseName)) {
            if ($killExistingInstance === false) {
                return $this->getLuceneIndex($databaseName);
            }

            $this->dropLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS);
        }

        $indexDir = $this->createIndexDir($databaseName);

        \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
        \Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');

        $index = \Zend_Search_Lucene::create($indexDir);

        if (!$index instanceof \Zend_Search_Lucene_Proxy) {
            throw new \Exception('Unable to create lucene database "%s"');
        }

        return $this->getLuceneIndex($databaseName);

    }

    public function riseGenesisIndexToStable(string $databaseName)
    {
        if (!$this->indexExists($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS)) {
            throw new \Exception(sprintf('lucene database "%s" does not exists in genesis', $databaseName));
        }

        $genesisIndex = $this->buildIndexPath($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS);
        $stableIndex = $this->buildIndexPath($databaseName, ConfigurationInterface::INDEX_BASE_STABLE);

        // remove stable index dir
        $this->dropLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_STABLE);

        // recreate empty stable index dir
        $this->createIndexDir($databaseName, ConfigurationInterface::INDEX_BASE_STABLE);

        // copy genesis to stable
        $this->filesystem->mirror($genesisIndex, $stableIndex, null, ['override' => true, 'delete' => true]);

        // optimize
        $this->optimizeLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_STABLE);
    }

    public function optimizeLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        if (!$this->indexExists($databaseName, $state)) {
            return;
        }

        $index = $this->getLuceneIndex($databaseName, $state);

        // optimize lucene index for better performance
        $index->optimize();

        //clean up
        $index->removeReference();
    }

    public function getLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $indexDir = $this->createIndexDir($databaseName, $state);

        return \Zend_Search_Lucene::open($indexDir);
    }

    public function dropLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $indexDir = $this->buildIndexPath($databaseName, $state);

        if (!$this->filesystem->exists($indexDir)) {
            return;
        }

        $this->filesystem->remove($indexDir);
    }

    protected function indexExists(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $indexDir = $this->createIndexDir($databaseName, $state);

        try {
            \Zend_Search_Lucene::open($indexDir);
            return true;
        } catch (\Zend_Search_Lucene_Exception $e) {
            // fail silently
        }

        return false;
    }

    protected function createIndexDir($databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $path = $this->buildIndexPath($databaseName, $state);

        if ($this->filesystem->exists($path)) {
            return $path;
        }

        $this->filesystem->mkdir($path, 0755);

        return $path;
    }

    protected function buildIndexPath($databaseName, $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        return sprintf('%s/%s', $state, $databaseName);
    }

}

