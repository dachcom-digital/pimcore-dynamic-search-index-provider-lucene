<?php

namespace DsLuceneBundle\Exception;

final class LuceneException extends \Exception
{
    public function __construct(string $message, ?\Throwable $previousException = null)
    {
        parent::__construct(message: sprintf('Lucene Exception: %s', $message), previous: $previousException);
    }
}
