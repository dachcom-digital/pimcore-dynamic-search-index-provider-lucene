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

namespace DsLuceneBundle\Lucene\Analyzer;

use Org\Heigl\Hyphenator;

class Syllable extends CaseInsensitive
{
    protected ?Hyphenator\Options $options = null;
    protected ?Hyphenator\Hyphenator $hypenator = null;

    /**
     * @param string $data
     * @param string $encoding
     */
    public function setInput($data, $encoding = '')
    {
        $hyphenator = $this->getHyphenator();
        $hyphenator->setOptions($this->getHyphenatorOptions());

        if (is_numeric($data)) {
            $this->setRealInput([], $data, $encoding);

            return;
        }

        $splitData = [];
        $terms = explode(' ', $data);

        $hyphenTerms = [];
        foreach ($terms as $term) {
            $hyphenated = $hyphenator->hyphenate($term);

            if (is_array($hyphenated)) {
                $hyphenTerms = array_merge($hyphenTerms, $hyphenated);
            } elseif (is_string($hyphenated)) {
                $hyphenTerms[] = $hyphenated;
            }
        }

        if (count($hyphenTerms) > 0) {
            $splitData = array_merge($splitData, $hyphenTerms);
        } else {
            $splitData[] = $data;
        }

        $terms = [];
        foreach ($splitData as $hyphenTerm) {
            $terms = array_merge($terms, explode_and_trim(' ', $hyphenTerm));
        }

        $terms = array_unique($terms);

        $this->setRealInput($terms, $data, $encoding);
    }

    /**
     * @param array  $terms
     * @param string $originalData
     * @param string $encoding
     */
    public function setRealInput(array $terms, $originalData, $encoding = '')
    {
        $this->_input = count($terms) === 0 ? $originalData : sprintf('%s %s', $originalData, join(' ', $terms));
        $this->_encoding = $encoding;

        $this->reset();
    }

    public function setHyphenatorOptions(Hyphenator\Options $options): void
    {
        $this->options = $options;
    }

    public function getHyphenator(): Hyphenator\Hyphenator
    {
        if ($this->hypenator instanceof Hyphenator\Hyphenator) {
            return $this->hypenator;
        }

        $this->hypenator = new Hyphenator\Hyphenator();

        return $this->hypenator;
    }

    public function getHyphenatorOptions(): Hyphenator\Options
    {
        if ($this->options instanceof Hyphenator\Options) {
            return $this->options;
        }

        $o = new Hyphenator\Options();
        $o->setHyphen(' ')
            ->setRightMin(4)
            ->setLeftMin(2)
            ->setWordMin(5)
            ->setFilters('NonStandard')
            ->setTokenizers(['Whitespace', 'Punctuation']);

        return $o;
    }
}
