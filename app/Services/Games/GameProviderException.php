<?php

namespace App\Services\Games;

use RuntimeException;

class GameProviderException extends RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode = 'PROVIDER_ERROR', private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}
