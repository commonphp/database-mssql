<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL\Exceptions;

use CommonPHP\Database\Exceptions\QueryException;
use Throwable;

class MssqlQueryException extends QueryException
{
    public static function forOperation(string $operation, string $query, Throwable $previous): self
    {
        return new self(
            'MSSQL query operation "' . $operation . '" failed for query: ' . self::summarize($query),
            previous: $previous,
        );
    }

    public static function forPrepareFailure(string $query): self
    {
        return new self('MSSQL could not prepare query: ' . self::summarize($query));
    }

    public static function forBinding(string|int $parameter, string $query): self
    {
        return new self(
            'MSSQL could not bind parameter "' . $parameter . '" for query: ' . self::summarize($query),
        );
    }

    public static function forInvalidParameter(string|int $parameter, string $message): self
    {
        return new self('Invalid MSSQL query parameter "' . $parameter . '": ' . $message);
    }

    private static function summarize(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? $query);

        return strlen($query) > 160 ? substr($query, 0, 157) . '...' : $query;
    }
}
