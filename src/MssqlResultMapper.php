<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Database\Enums\FetchMode;
use PDOStatement;

final class MssqlResultMapper
{
    /**
     * @return array<string|int, mixed>|false
     */
    public function fetchOne(PDOStatement $statement): array|false
    {
        $row = $statement->fetch(FetchMode::FETCH_ASSOC->value);

        return is_array($row) ? $row : false;
    }

    /**
     * @return list<array<string|int, mixed>|object|scalar|null>
     */
    public function fetchAll(
        PDOStatement $statement,
        FetchMode $fetchMode = FetchMode::FETCH_ASSOC,
    ): array {
        $rows = $statement->fetchAll($fetchMode->value);

        return is_array($rows) ? $rows : [];
    }

    public function fetchScalar(PDOStatement $statement, mixed $default = null): mixed
    {
        $value = $statement->fetchColumn();

        return $value === false ? $default : $value;
    }
}
