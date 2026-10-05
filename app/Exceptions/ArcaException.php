<?php

namespace App\Exceptions;

use RuntimeException;

class ArcaException extends RuntimeException
{
    protected ?string $arcaCode = null;

    public function __construct(string $message, ?string $arcaCode = null, int $code = 0, ?\Throwable $previous = null)
    {
        $this->arcaCode = $arcaCode;
        parent::__construct($message, $code, $previous);
    }

    public function getArcaCode(): ?string
    {
        return $this->arcaCode;
    }

    public function getErrorCode(): ?string
    {
        return $this->arcaCode;
    }
}
