<?php

namespace DsLuceneBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use ZendSearch\Lucene\Analysis\Analyzer\AnalyzerInterface;

class AnalzyerEvent extends Event
{
    protected ?string $locale = null;
    protected bool $isIndexMode;
    protected ?AnalyzerInterface $analyzer = null;

    public function __construct(?string $locale = null, bool $isIndexMode = false)
    {
        $this->locale = $locale;
        $this->isIndexMode = $isIndexMode;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function isIndexMode(): bool
    {
        return $this->isIndexMode;
    }

    public function getAnalyzer(): ?AnalyzerInterface
    {
        return $this->analyzer;
    }

    public function setAnalyzer(AnalyzerInterface $analyzer): void
    {
        $this->analyzer = $analyzer;
    }
}
