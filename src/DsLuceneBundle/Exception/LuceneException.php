<?php

namespace DsLuceneBundle\Exception;

final class LuceneException extends \Exception
{
    public function __construct(string $message, \Exception $previousException = null)
    {
        parent::__construct(sprintf('Lucene Exception: %s', $message), 0, $previousException);
    }
}
