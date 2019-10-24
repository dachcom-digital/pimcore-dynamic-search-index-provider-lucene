<?php

namespace DsLuceneBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use ZendSearch\Lucene\Analysis\Analyzer\AnalyzerInterface;

class AnalzyerEvent extends Event
{
    /**
     * @var string|null
     */
    protected $locale;

    /**
     * @var bool
     */
    protected $isIndexMode;

    /**
     * @var AnalyzerInterface
     */
    protected $analyzer;

    /**
     * @param string|null $locale
     * @param bool        $isIndexMode
     */
    public function __construct(?string $locale = null, $isIndexMode = false)
    {
        $this->locale = $locale;
        $this->isIndexMode = $isIndexMode;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return bool
     */
    public function isIndexMode()
    {
        return $this->isIndexMode;
    }

    /**
     * @return string
     */
    public function getAnalyzer()
    {
        return $this->analyzer;
    }

    /**
     * @param AnalyzerInterface $analyzer
     */
    public function setAnalyzer(AnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }
}
