<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL\Exceptions;

use CommonPHP\Database\Exceptions\ConnectionException;
use CommonPHP\Drivers\Database\MSSQL\MssqlConnectionOptions;
use Throwable;

class MssqlConnectionException extends ConnectionException
{
    public static function forInvalidOptions(string $message): self
    {
        return new self($message);
    }

    public static function forConnection(MssqlConnectionOptions $options, Throwable $previous): self
    {
        return new self(
            'MSSQL connection to "' . $options->database() . '" at "' . $options->endpoint() . '" failed.',
            previous: $previous,
        );
    }
}
