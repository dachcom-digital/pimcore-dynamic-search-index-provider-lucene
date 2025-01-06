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

namespace DsLuceneBundle\Lucene\Filter\Stemming;

use Wamania\Snowball\Stemmer;
use ZendSearch\Lucene\Analysis\Token;
use ZendSearch\Lucene\Analysis\TokenFilter\TokenFilterInterface;

class SnowBallStemmingFilter implements TokenFilterInterface
{
    public const MIN_TOKEN_LENGTH = 1;

    protected ?string $locale = null;
    protected array $cache = [];
    protected array $mapping = [
        'da' => 'Danish',
        'nl' => 'Dutch',
        'en' => 'English',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'no' => 'Norwegian',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'es' => 'Spanish',
        'sv' => 'Swedish'
    ];

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(Token $srcToken)
    {
        $termText = $srcToken->getTermText();

        $newTokenString = !is_numeric($termText) ? $this->stem($termText) : $termText;

        if (is_null($newTokenString)) {
            return $srcToken;
        }

        $newToken = new Token($newTokenString, $srcToken->getStartOffset(), $srcToken->getEndOffset());

        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }

    protected function stem(string $word): ?string
    {
        if (extension_loaded('stemmer')) {
            $stemmedWord = $this->stemByExtension($word);
        } else {
            $stemmedWord = $this->stemByPhpSnowball($word);
        }

        return $stemmedWord;
    }

    protected function stemByExtension(string $word): ?string
    {
        $stemWordFunction = 'stemword';
        if (function_exists($stemWordFunction)) {
            return call_user_func($stemWordFunction, $word, $this->locale, 'UTF_8');
        }

        return null;
    }

    protected function stemByPhpSnowball(string $sourceStr): ?string
    {
        if (strlen($sourceStr) < self::MIN_TOKEN_LENGTH) {
            return null;
        }

        $snowBallClass = $this->getStemmingClass();
        if (!$snowBallClass instanceof Stemmer) {
            return null;
        }

        $stem = $snowBallClass->stem($sourceStr);

        if (empty($stem)) {
            return $sourceStr;
        }

        return $stem;
    }

    protected function getStemmingClass(): ?Stemmer
    {
        $locale = $this->locale;
        if (is_string($locale) && str_contains($locale, '_')) {
            $localeFragments = explode('_', $locale);
            $locale = $localeFragments[0];
        }

        if ($locale === null) {
            $locale = 'en';
        }

        if (isset($this->cache[$locale])) {
            return $this->cache[$locale];
        }

        if (!isset($this->mapping[$locale])) {
            return null;
        }

        $stemmingClass = sprintf('\Wamania\Snowball\%s', $this->mapping[$locale]);
        $stemmer = new $stemmingClass();

        $this->cache[$locale] = $stemmer;

        return $stemmer;
    }
}
