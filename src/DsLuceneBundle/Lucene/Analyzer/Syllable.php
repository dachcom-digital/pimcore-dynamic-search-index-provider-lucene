<?php

namespace DsLuceneBundle\Lucene\Analyzer;

use Org\Heigl\Hyphenator;

class Syllable extends CaseInsensitive
{
    /**
     * @var Hyphenator\Options
     */
    protected $options;

    /**
     * @var Hyphenator\Hyphenator
     */
    protected $hypenator;

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

    /**
     * @param Hyphenator\Options $options
     */
    public function setHyphenatorOptions(Hyphenator\Options $options)
    {
        $this->options = $options;
    }

    /**
     * @return Hyphenator\Hyphenator
     */
    public function getHyphenator()
    {
        if ($this->hypenator instanceof Hyphenator\Hyphenator) {
            return $this->hypenator;
        }

        $this->hypenator = new Hyphenator\Hyphenator();

        return $this->hypenator;
    }

    /**
     * @return Hyphenator\Options
     */
    public function getHyphenatorOptions()
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
