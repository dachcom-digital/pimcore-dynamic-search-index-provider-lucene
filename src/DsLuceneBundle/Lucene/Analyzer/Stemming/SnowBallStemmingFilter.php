<?php

namespace DsLuceneBundle\Lucene\Analyzer\Stemming;

use Wamania\Snowball\Stemmer;
use ZendSearch\Lucene\Analysis\Token;
use ZendSearch\Lucene\Analysis\TokenFilter\TokenFilterInterface;

class SnowBallStemmingFilter implements TokenFilterInterface
{
    /**
     * @var int
     */
    const MIN_TOKEN_LENGTH = 1;

    /**
     * @var array
     */
    protected $mapping = [
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

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param string $locale
     */
    public function setLocale(string $locale)
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

    /**
     * @param string $word
     *
     * @return string|null
     * @throws \Exception
     */
    protected function stem($word)
    {
        if (extension_loaded('stemmer')) {
            $stemmedWord = $this->stemByExtension($word);
        } else {
            $stemmedWord = $this->stemByPhpSnowball($word);
        }

        return $stemmedWord;
    }

    /**
     * @param string $word
     *
     * @return string|null
     */
    protected function stemByExtension($word)
    {
        return stemword($word, $this->locale, 'UTF_8');
    }

    /**
     * @param string $sourceStr
     *
     * @return string|null
     * @throws \Exception
     */
    protected function stemByPhpSnowball($sourceStr)
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

    /**
     * @return string|null
     * @throws \Exception
     */
    protected function getStemmingClass()
    {
        $locale = $this->locale;
        if (strpos($this->locale, '_') !== false) {
            $localeFragments = explode('_', $this->locale);
            $locale = $localeFragments[0];
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
