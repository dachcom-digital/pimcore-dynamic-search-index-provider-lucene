<?php

namespace DsLuceneBundle\Lucene\Analyzer;

use ZendSearch\Lucene\Analysis\Analyzer\Common\AbstractCommon;
use ZendSearch\Lucene\Analysis\Analyzer\Common\Utf8\CaseInsensitive;
use ZendSearch\Lucene\Analysis\TokenFilter\StopWords;

class DefaultAnalyzerBuilder
{
    /**
     * @param array       $analyzerOptions
     * @param string|null $locale
     * @param bool        $isIndexMode
     *
     * @return CaseInsensitive
     */
    public function build(array $analyzerOptions, ?string $locale = null, $isIndexMode = false)
    {
        $analyzer = new CaseInsensitive();

        $builtLocale = null;

        if (is_string($locale)) {
            $builtLocale = $locale;
        }

        if (isset($analyzerOptions['forced_locale']) && is_string($analyzerOptions['forced_locale'])) {
            $builtLocale = $analyzerOptions['forced_locale'];
        }

        $this->addStopWordsFilter($analyzer, $isIndexMode, $builtLocale, $analyzerOptions);
        $this->addStemmingFilter($analyzer, $isIndexMode, $builtLocale, $analyzerOptions);

        return $analyzer;
    }

    /**
     * @param AbstractCommon $analyzer
     * @param bool           $isIndexMode
     * @param string|null    $currentLocale
     * @param array          $analyzerOptions
     */
    public function addStopWordsFilter(AbstractCommon $analyzer, bool $isIndexMode, ?string $currentLocale, array $analyzerOptions)
    {
        $stopWordOptions = isset($analyzerOptions['stop_words']) && is_array($analyzerOptions['stop_words']) ? $analyzerOptions['stop_words'] : [];

        if (empty($stopWordOptions)) {
            return;
        }

        $stopWordsLibraries = isset($stopWordOptions['libraries']) && is_array($stopWordOptions['libraries']) ? $stopWordOptions['libraries'] : [];

        $onIndexTime = isset($stopWordOptions['on_index_time']) && is_bool($stopWordOptions['on_index_time']) ? $stopWordOptions['on_index_time'] : true;
        $onQueryTime = isset($stopWordOptions['on_query_time']) && is_bool($stopWordOptions['on_query_time']) ? $stopWordOptions['on_query_time'] : true;

        if ($isIndexMode === true && $onIndexTime === false) {
            return;
        } elseif ($isIndexMode === false && $onQueryTime === false) {
            return;
        }

        if (empty($stopWordsLibraries)) {
            return;
        }

        // we cant add stop words without valid locale
        if ($currentLocale === null) {
            return;
        }

        foreach ($stopWordsLibraries as $library) {
            $locale = isset($library['locale']) ? $library['locale'] : null;
            $file = isset($library['file']) ? $library['file'] : null;

            if (empty($locale) || $locale !== $currentLocale) {
                continue;
            }

            $stopWordsFilter = new StopWords();
            $stopWordsFilter->loadFromFile($this->parseFilePath($file));
            $analyzer->addFilter($stopWordsFilter);
        }
    }

    /**
     * @param AbstractCommon $analyzer
     * @param bool           $isIndexMode
     * @param string|null    $currentLocale
     * @param array          $analyzerOptions
     */
    public function addStemmingFilter(AbstractCommon $analyzer, bool $isIndexMode, ?string $currentLocale, array $analyzerOptions)
    {
        $filterBlocks = isset($analyzerOptions['filter']) && is_array($analyzerOptions['filter']) ? $analyzerOptions['filter'] : [];

        if (empty($filterBlocks)) {
            return;
        }

        foreach ($filterBlocks as $filterBlock) {
            $onIndexTime = isset($filterBlock['on_index_time']) && is_bool($filterBlock['on_index_time']) ? $filterBlock['on_index_time'] : true;
            $onQueryTime = isset($filterBlock['on_query_time']) && is_bool($filterBlock['on_query_time']) ? $filterBlock['on_query_time'] : true;
            $isLocaleAware = isset($filterBlock['locale_aware']) && is_bool($filterBlock['locale_aware']) ? $filterBlock['locale_aware'] : false;
            $filterClass = isset($filterBlock['class']) ? $filterBlock['class'] : null;

            if ($isIndexMode === true && $onIndexTime === false) {
                continue;
            } elseif ($isIndexMode === false && $onQueryTime === false) {
                continue;
            }

            if ($filterClass === null) {
                continue;
            }

            if ($isLocaleAware === true && $currentLocale === null) {
                continue;
            }

            $filter = new $filterClass();
            if ($isLocaleAware === true && method_exists($filter, 'setLocale')) {
                $filter->setLocale($currentLocale);
            }

            $analyzer->addFilter($filter);
        }
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
            [__DIR__ . '/StopWords/libraries'],
            $path
        );
    }
}
