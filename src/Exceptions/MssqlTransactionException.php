<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL\Exceptions;

use CommonPHP\Database\Exceptions\TransactionException;
use Throwable;

class MssqlTransactionException extends TransactionException
{
    public static function forOperation(string $operation, Throwable $previous): self
    {
        return new self('MSSQL transaction could not ' . $operation . '.', previous: $previous);
    }

    public static function forFailure(string $operation): self
    {
        return new self('MSSQL transaction could not ' . $operation . '.');
    }
}
