<?php

namespace DsLuceneBundle\Service;

use DsLuceneBundle\Configuration\ConfigurationInterface;
use DsLuceneBundle\DsLuceneBundle;
use DsLuceneBundle\Exception\LuceneException;
use DsLuceneBundle\Factory\AnalyzerFactory;
use DynamicSearchBundle\Exception\ProviderException;
use Symfony\Component\Filesystem\Filesystem;
use ZendSearch\Lucene\Analysis\Analyzer\Analyzer;
use ZendSearch\Lucene\Lucene;
use ZendSearch\Exception\ExceptionInterface;
use ZendSearch\Lucene\Search\QueryParser;
use ZendSearch\Lucene\SearchIndexInterface;

class LuceneStorageBuilder
{
    protected string $basePath;
    protected Filesystem $filesystem;
    protected AnalyzerFactory $analyzerFactory;

    public function __construct(string $basePath, AnalyzerFactory $analyzerFactory)
    {
        $this->basePath = $basePath;
        $this->filesystem = new Filesystem();
        $this->analyzerFactory = $analyzerFactory;
    }

    /**
     * @throws LuceneException
     */
    public function createGenesisIndex(array $providerOptions, bool $killExistingInstance = false): SearchIndexInterface
    {
        $databaseName = $providerOptions['database_name'];

        if ($this->indexExists($databaseName)) {
            if ($killExistingInstance === false) {
                return $this->getLuceneIndex($providerOptions);
            }

            $this->dropLuceneIndex($databaseName, ConfigurationInterface::INDEX_BASE_GENESIS);
        }

        $indexDir = $this->createIndexDir($databaseName);

        try {
            $index = Lucene::create($indexDir);
        } catch (ExceptionInterface $e) {
            throw new LuceneException(sprintf('Unable to create lucene database "%s". Error was: %s', $databaseName, $e), $e);
        }

        return $this->getLuceneIndex($providerOptions);
    }

    /**
     * @throws ProviderException
     */
    public function riseGenesisIndexToStable(array $providerOptions): void
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
     * @throws LuceneException
     */
    public function getLuceneIndex(
        array $providerOptions,
        string $state = ConfigurationInterface::INDEX_BASE_GENESIS,
        ?string $locale = null,
        bool $isIndexMode = false
    ): SearchIndexInterface {
        QueryParser::setDefaultEncoding('utf-8');

        try {
            $analyzer = $this->analyzerFactory->build($providerOptions['analyzer'], $locale, $isIndexMode);
        } catch (ExceptionInterface $e) {
            throw new LuceneException($e->getMessage(), $e);
        }

        Analyzer::setDefault($analyzer);

        $indexDir = $this->createIndexDir($providerOptions['database_name'], $state);

        return Lucene::open($indexDir);
    }

    public function dropLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS): void
    {
        $indexDir = $this->buildIndexPath($databaseName, $state);

        if (!$this->filesystem->exists($indexDir)) {
            return;
        }

        $this->filesystem->remove($indexDir);
    }

    public function optimizeLuceneIndex(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS): void
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

    public function indexExists(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS): bool
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

    protected function createIndexDir(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS): string
    {
        $path = $this->buildIndexPath($databaseName, $state);

        if ($this->filesystem->exists($path)) {
            return $path;
        }

        $this->filesystem->mkdir($path, 0755);

        return $path;
    }

    protected function buildIndexPath(string $databaseName, string $state = ConfigurationInterface::INDEX_BASE_GENESIS): string
    {
        return sprintf('%s/%s/%s', $this->basePath, $state, $databaseName);
    }
}
