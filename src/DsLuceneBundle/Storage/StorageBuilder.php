<?php

namespace DsLuceneBundle\Storage;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DynamicSearchBundle\Exception\ProviderException;
use Symfony\Component\Filesystem\Filesystem;

class StorageBuilder
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param string $databaseName
     * @param bool   $killExistingInstance
     *
     * @return \Zend_Search_Lucene_Interface
     * @throws ProviderException
     */
    public function createGenesisIndex(string $databaseName, $killExistingInstance = false)
    {
        if ($this->indexExists($databaseName)) {
            if ($killExistingInstance === false) {
                return $this->getLuceneIndex($databaseName);
            }

            $this->dropLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS);
        }

        $indexDir = $this->createIndexDir($databaseName);

        $index = null;

        try {
            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
            \Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
            $index = \Zend_Search_Lucene::create($indexDir);
        } catch (\Zend_Search_Exception $e) {
            throw new ProviderException(sprintf('Unable to create lucene database "%s". Error was: %s', $databaseName, $e), DsLuceneBundle::PROVIDER_NAME, $e);
        }

        if (!$index instanceof \Zend_Search_Lucene_Proxy) {
            throw new ProviderException(sprintf('Unable to create lucene database "%s"', $databaseName), DsLuceneBundle::PROVIDER_NAME);
        }

        return $this->getLuceneIndex($databaseName);

    }

    /**
     * @param string $databaseName
     *
     * @throws ProviderException
     */
    public function riseGenesisIndexToStable(string $databaseName)
    {
        if (!$this->indexExists($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS)) {
            throw new ProviderException(sprintf('lucene database "%s" does not exists in genesis', $databaseName), DsLuceneBundle::PROVIDER_NAME);
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

    /**
     * @param string $databaseName
     * @param string $state
     */
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

    /**
     * @param string $databaseName
     * @param string $state
     *
     * @return \Zend_Search_Lucene_Interface
     */
    public function getLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $indexDir = $this->createIndexDir($databaseName, $state);

        return \Zend_Search_Lucene::open($indexDir);
    }

    /**
     * @param string $databaseName
     * @param string $state
     */
    public function dropLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $indexDir = $this->buildIndexPath($databaseName, $state);

        if (!$this->filesystem->exists($indexDir)) {
            return;
        }

        $this->filesystem->remove($indexDir);
    }

    /**
     * @param string $databaseName
     * @param string $state
     *
     * @return bool
     */
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

    /**
     * @param string $databaseName
     * @param string $state
     *
     * @return string
     */
    protected function createIndexDir($databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        $path = $this->buildIndexPath($databaseName, $state);

        if ($this->filesystem->exists($path)) {
            return $path;
        }

        $this->filesystem->mkdir($path, 0755);

        return $path;
    }

    /**
     * @param string $databaseName
     * @param string $state
     *
     * @return string
     */
    protected function buildIndexPath($databaseName, $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        return sprintf('%s/%s', $state, $databaseName);
    }

}

