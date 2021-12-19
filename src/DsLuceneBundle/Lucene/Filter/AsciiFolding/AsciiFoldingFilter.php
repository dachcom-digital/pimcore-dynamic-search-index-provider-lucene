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

    protected function process(string $word): ?string
    {
        if (!function_exists('transliterator_transliterate')) {
            return $word;
        }

        $trans = transliterator_transliterate('Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove', $word);

        if ($trans === false) {
            return null;
        }

        return $trans;
    }
}
