<?php

namespace DsLuceneBundle\Lucene\Filter\AsciiFolding;

use ZendSearch\Lucene\Analysis\Token;
use ZendSearch\Lucene\Analysis\TokenFilter\TokenFilterInterface;

class AsciiFoldingFilter implements TokenFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize(Token $srcToken)
    {
        $termText = $srcToken->getTermText();

        $newTokenString = !is_numeric($termText) ? $this->process($termText) : $termText;

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
     * @return string
     */
    protected function process($word)
    {
        if (!function_exists('transliterator_transliterate')) {
            return $word;
        }

        return transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $word);
    }
}
