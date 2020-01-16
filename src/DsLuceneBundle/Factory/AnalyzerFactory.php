<?php

namespace DsLuceneBundle\Factory;

use DsLuceneBundle\DsLuceneEvents;
use DsLuceneBundle\Event\AnalzyerEvent;
use DsLuceneBundle\Lucene\Analyzer\CaseInsensitive;
use DsLuceneBundle\Lucene\Filter\Stemming\SnowBallStemmingFilter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use ZendSearch\Lucene\Analysis\Analyzer\Common\AbstractCommon;
use ZendSearch\Lucene\Analysis\TokenFilter\StopWords;
use ZendSearch\Lucene\Analysis\TokenFilter\TokenFilterInterface;

class AnalyzerFactory
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array       $analyzerOptions
     * @param string|null $locale
     * @param bool        $isIndexMode
     *
     * @return CaseInsensitive
     */
    public function build(array $analyzerOptions, ?string $locale = null, $isIndexMode = false)
    {
        $builtLocale = null;

        if (is_string($locale)) {
            $builtLocale = $locale;
        }

        if (isset($analyzerOptions['forced_locale']) && is_string($analyzerOptions['forced_locale'])) {
            $builtLocale = $analyzerOptions['forced_locale'];
        }

        $event = new AnalzyerEvent($locale, $isIndexMode);
        $this->eventDispatcher->dispatch(DsLuceneEvents::BUILD_LUCENE_ANALYZER, $event);

        $analyzer = $event->getAnalyzer();

        if (!$analyzer instanceof AbstractCommon) {
            $analyzer = new CaseInsensitive();
        }

        if (isset($analyzerOptions['filter']) && is_array($analyzerOptions['filter'])) {
            $this->addAnalyzerFilter($analyzer, $analyzerOptions['filter'], $isIndexMode, $builtLocale);
        }

        return $analyzer;
    }

    /**
     * @param AbstractCommon $analyzer
     * @param array          $filterData
     * @param bool           $isIndexMode
     * @param string|null    $currentLocale
     */
    public function addAnalyzerFilter(AbstractCommon $analyzer, array $filterData, bool $isIndexMode, ?string $currentLocale)
    {
        foreach ($filterData as $filterName => $filterOptions) {
            $filter = null;

            if ($this->filterIsActive($filterOptions, $isIndexMode) === false) {
                continue;
            }

            if ($filterName === 'stop_words') {
                $filter = $this->buildStopWordsFilter($currentLocale, $filterOptions);
            } else {
                $filter = $this->buildDefaultFilter($currentLocale, $filterOptions);
            }

            if ($filter instanceof TokenFilterInterface) {
                $analyzer->addFilter($filter);
            }
        }
    }

    /**
     * @param string|null $currentLocale
     * @param array       $filterOptions
     *
     * @return TokenFilterInterface|null
     */
    public function buildStopWordsFilter(?string $currentLocale, array $filterOptions)
    {
        $stopWordsFilter = null;

        if (empty($filterOptions)) {
            return null;
        }

        $stopWordsLibraries = isset($filterOptions['libraries']) && is_array($filterOptions['libraries']) ? $filterOptions['libraries'] : [];

        if (empty($stopWordsLibraries)) {
            return null;
        }

        // we cant add stop words without valid locale
        if ($currentLocale === null) {
            return null;
        }

        foreach ($stopWordsLibraries as $library) {
            $locale = isset($library['locale']) ? $library['locale'] : null;
            $file = isset($library['file']) ? $library['file'] : null;

            if (empty($locale) || $locale !== $currentLocale) {
                continue;
            }

            $stopWordsFilter = new StopWords();
            $stopWordsFilter->loadFromFile($this->parseFilePath($file));
        }

        return $stopWordsFilter;
    }

    /**
     * @param string|null $currentLocale
     * @param array       $filterOptions
     *
     * @return TokenFilterInterface|null
     */
    public function buildDefaultFilter(?string $currentLocale, array $filterOptions)
    {
        $filter = null;

        $isLocaleAware = isset($filterOptions['locale_aware']) && is_bool($filterOptions['locale_aware']) ? $filterOptions['locale_aware'] : false;
        $filterClass = isset($filterOptions['class']) ? $filterOptions['class'] : SnowBallStemmingFilter::class;

        if ($filterClass === null) {
            return null;
        }

        if ($isLocaleAware === true && $currentLocale === null) {
            return null;
        }

        $filter = new $filterClass();
        if ($isLocaleAware === true && method_exists($filter, 'setLocale')) {
            $filter->setLocale($currentLocale);
        }

        return $filter;
    }

    /**
     * @param string $path
     *
     * @return mixed
     */
    protected function parseFilePath($path)
    {
        return str_replace(
            ['%dsl_stop_words_lib_path%'],
            [realpath(__DIR__ . '/../Lucene/Filter/StopWords/libraries')],
            $path
        );
    }

    /**
     * @param array $filterOptions
     * @param bool  $isIndexMode
     *
     * @return bool
     */
    protected function filterIsActive(array $filterOptions, bool $isIndexMode)
    {
        $onIndexTime = isset($filterOptions['on_index_time']) && is_bool($filterOptions['on_index_time']) ? $filterOptions['on_index_time'] : true;
        $onQueryTime = isset($filterOptions['on_query_time']) && is_bool($filterOptions['on_query_time']) ? $filterOptions['on_query_time'] : true;

        if ($isIndexMode === true && $onIndexTime === false) {
            return false;
        } elseif ($isIndexMode === false && $onQueryTime === false) {
            return false;
        }

        return true;
    }
}
