<?php

/*
 * This source file is available under two different licenses:
 *   - GNU General Public License version 3 (GPLv3)
 *   - DACHCOM Commercial License (DCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) DACHCOM.DIGITAL AG (https://www.dachcom-digital.com)
 * @license    GPLv3 and DCL
 */

namespace DsLuceneBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use ZendSearch\Lucene\Analysis\Analyzer\AnalyzerInterface;

class AnalyzerEvent extends Event
{
    protected ?AnalyzerInterface $analyzer = null;

    public function __construct(
        protected ?string $locale = null,
        protected bool $isIndexMode = false
    ) {
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
