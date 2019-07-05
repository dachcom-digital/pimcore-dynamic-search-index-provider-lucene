<?php

namespace DsLuceneBundle\Exception;

final class LuceneException extends \Exception
{
    /**
     * @param string          $message
     * @param \Exception|null $previousException
     */
    public function __construct($message, $previousException = null)
    {
        parent::__construct(sprintf('Lucene Exception: %s', $message), 0, $previousException);
    }
}
