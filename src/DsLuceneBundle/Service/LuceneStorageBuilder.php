<?php

namespace DsLuceneBundle\Service;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Exception\LuceneException;
use DsLuceneBundle\Lucene\Analyzer\DefaultAnalyzerBuilder;
use DynamicSearchBundle\Exception\ProviderException;
use Symfony\Component\Filesystem\Filesystem;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Exception\ExceptionInterface;
use ZendSearch\Lucene\Search\QueryParser;
use ZendSearch\Lucene\SearchIndexInterface;

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
     * @param array $providerOptions
     * @param bool  $killExistingInstance
     *
     * @return SearchIndexInterface
     *
     * @throws LuceneException
     */
    public function createGenesisIndex(array $providerOptions, $killExistingInstance = false)
    {
        $databaseName = $providerOptions['database_name'];

        if ($this->indexExists($databaseName)) {
            if ($killExistingInstance === false) {
                return $this->getLuceneIndex($providerOptions);
            }

            $this->dropLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS);
        }

        $indexDir = $this->createIndexDir($databaseName);

        $index = null;

        try {
            $index = Lucene::create($indexDir);
        } catch (ExceptionInterface $e) {
            throw new LuceneException(sprintf('Unable to create lucene database "%s". Error was: %s', $databaseName, $e), $e);
        }

        if (!$index instanceof SearchIndexInterface) {
            throw new LuceneException(sprintf('Unable to create lucene database "%s"', $databaseName));
        }

        return $this->getLuceneIndex($providerOptions);
    }

    /**
     * @param array $providerOptions
     *
     * @throws ProviderException
     * @throws LuceneException
     */
    public function riseGenesisIndexToStable(array $providerOptions)
    {
        $databaseName = $providerOptions['database_name'];

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
     * @param array       $providerOptions
     * @param string      $state
     * @param string|null $locale
     * @param bool        $isIndexMode
     *
     * @return SearchIndexInterface
     *
     * @throws LuceneException
     */
    public function getLuceneIndex(array $providerOptions, string $state = ConfigurationInterface::INDEX_BASE_GENESIS, ?string $locale = null, $isIndexMode = false)
    {
        QueryParser::setDefaultEncoding('utf-8');

        $builder = new DefaultAnalyzerBuilder();

        try {
            $analyzer = $builder->build($providerOptions['analyzer'], $locale, $isIndexMode);
        } catch (ExceptionInterface $e) {
            throw new LuceneException($e->getMessage(), $e);
        }

        Analyzer::setDefault($analyzer);

        $indexDir = $this->createIndexDir($providerOptions['database_name'], $state);

        return Lucene::open($indexDir);
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
     */
    public function optimizeLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        if (!$this->indexExists($databaseName, $state)) {
            return;
        }

        $indexDir = $this->createIndexDir($databaseName, $state);
        $index = Lucene::open($indexDir);

        // commit changes
        $index->commit();

        // optimize lucene index for better performance
        $index->optimize();
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
            Lucene::open($indexDir);

            return true;
        } catch (ExceptionInterface $e) {
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
    protected function createIndexDir(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
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
    protected function buildIndexPath(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS)
    {
        return sprintf('%s/%s', $state, $databaseName);
    }
}
