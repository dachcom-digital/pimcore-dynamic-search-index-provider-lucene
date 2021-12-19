<?php

namespace DsLuceneBundle\Modifier;

/**
 * @see https://framework.zend.com/manual/2.1/en/modules/zendsearch.lucene.query-language.html
 */
class TermModifier
{
    /**
     * Default Behaviour:
     * Input: "my awesome search query"
     * Output: ["awesome", "search", "query"]
     */
    public function splitTerm(string $query, int $minPrefixLength = 3, int $maxTerms = 0): array
    {
        $terms = array_values(array_filter(explode(' ', $query), function ($t) use ($minPrefixLength) {
            return strlen($t) >= $minPrefixLength;
        }));

        $cleanTerms = [];

        foreach ($terms as $term) {
            preg_match_all('/[\p{L}\p{N}]+/u', $term, $match, PREG_OFFSET_CAPTURE);

            if (!is_array($match[0])) {
                $cleanTerms[] = $term;

                continue;
            }

            $specialTerms = [];
            foreach ($match[0] as $matchTerm) {
                $specialTerms[] = $matchTerm[0];
            }

            $cleanTerms = array_merge($cleanTerms, array_values(array_filter($specialTerms, function ($t) use ($minPrefixLength) {
                return strlen($t) >= $minPrefixLength;
            })));
        }

        return $maxTerms === 0 ? $cleanTerms : array_slice($cleanTerms, 0, $maxTerms);
    }

    public function isPhrasedQuery(string $query): bool
    {
        return preg_match('#^(\'|").+\1$#', $query) === 1;
    }

    public function escapeSpecialChars(string $str): string
    {
        $specialChars = ['\\', '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':'];

        foreach ($specialChars as $ch) {
            $str = str_replace($ch, "\\{$ch}", $str);
        }

        return $str;
    }

    public function removeSpecialOperators(string $str): string
    {
        $queryOperators = ['to', 'or', 'and', 'not'];

        $queryOperators = array_map(
            static function ($operator) {
                return " {$operator} ";
            },
            $queryOperators
        );

        return str_ireplace($queryOperators, ' ', $str);
    }
}
