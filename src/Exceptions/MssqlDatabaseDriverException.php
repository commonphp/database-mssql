<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL\Exceptions;

use CommonPHP\Database\Exceptions\DatabaseDriverException;
use Throwable;

class MssqlDatabaseDriverException extends DatabaseDriverException
{
    public static function forOperation(string $operation, Throwable $previous): self
    {
        return new self('MSSQL database driver operation "' . $operation . '" failed.', previous: $previous);
    }
}
