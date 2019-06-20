<?php

namespace DsLuceneBundle\OutputChannel\Modifier\Filter;

use DynamicSearchBundle\OutputChannel\Modifier\OutputChannelModifierFilterInterface;

class QueryCleanTermFilter implements OutputChannelModifierFilterInterface
{
    /**
     * {@inheritDoc}
     */
    public function dispatchFilter(string $outputChannelName, array $options)
    {
        return trim(
            preg_replace('|\s{2,}|', ' ',
                preg_replace('|[^\p{L}\p{N} ]/u|', ' ',
                    strtolower(
                        strip_tags(
                            str_replace("\n", ' ', $options['raw_term'])
                        )
                    )
                )
            )
        );
    }
}