<?php

namespace App\Exceptions;

use RuntimeException;

class JwtException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid_token')
    {
        parent::__construct($message);
    }
}
