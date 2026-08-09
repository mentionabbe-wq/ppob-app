<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(string $message = 'Provider tidak dapat dihubungi.', private readonly array $context = [])
    {
        parent::__construct($message, 502);
    }

    public function context(): array
    {
        return $this->context;
    }
}
