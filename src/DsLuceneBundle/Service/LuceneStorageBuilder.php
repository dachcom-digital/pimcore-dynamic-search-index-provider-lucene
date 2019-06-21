<?php

namespace DsLuceneBundle\Service;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Exception\LuceneException;
use DynamicSearchBundle\Exception\ProviderException;
use Symfony\Component\Filesystem\Filesystem;

class LuceneStorageBuilder
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
     * @throws LuceneException
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
            $index = \Zend_Search_Lucene::create($indexDir);
        } catch (\Zend_Search_Lucene_Exception $e) {
            throw new LuceneException(sprintf('Unable to create lucene database "%s". Error was: %s', $databaseName, $e), $e);
        }

        if (!$index instanceof \Zend_Search_Lucene_Proxy) {
            throw new LuceneException(sprintf('Unable to create lucene database "%s"', $databaseName));
        }

        return $this->getLuceneIndex($databaseName);

    }

    /**
     * @param string $databaseName
     *
     * @throws ProviderException
     * @throws LuceneException
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
     *
     * @throws LuceneException
     */
    public function optimizeLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        if (!$this->indexExists($databaseName, $state)) {
            return;
        }

        $index = $this->getLuceneIndex($databaseName, $state);

        // commit changes
        $index->commit();

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
     * @throws LuceneException
     */
    public function getLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        try {
            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive());
            \Zend_Search_Lucene_Search_QueryParser::setDefaultEncoding('UTF-8');
        } catch (\Zend_Search_Exception $e) {
            throw new LuceneException($e->getMessage(), $e);
        }

        $indexDir = $this->createIndexDir($databaseName, $state);

        $index = \Zend_Search_Lucene::open($indexDir);

        return $index;
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
    public function indexExists(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
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

