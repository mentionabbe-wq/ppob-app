<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $message = 'Saldo tidak mencukupi.')
    {
        parent::__construct($message, 422);
    }
}
