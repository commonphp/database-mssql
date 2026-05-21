<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlQueryException;

final class MssqlQueryCompiler
{
    /**
     * @param string|list<string> $columns
     */
    public function compileSelect(
        string $table,
        string|array $columns = ['*'],
        ?string $where = null,
        ?int $limit = null,
        ?int $offset = null,
    ): string {
        $columns = is_string($columns) ? [$columns] : $columns;
        $this->assertLimitAndOffset($limit, $offset);

        $selectPrefix = 'select';

        if ($limit !== null && $offset === null) {
            $selectPrefix .= ' top (' . $limit . ')';
        }

        $sql = $selectPrefix . ' ' . implode(', ', array_map($this->quoteIdentifier(...), $columns))
            . ' from ' . $this->quoteIdentifier($table);

        if ($where !== null && trim($where) !== '') {
            $sql .= ' where ' . trim($where);
        }

        if ($offset !== null) {
            $sql .= ' order by (select 0) offset ' . $offset . ' rows';

            if ($limit !== null) {
                $sql .= ' fetch next ' . $limit . ' rows only';
            }
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function compileInsert(string $table, array $values): string
    {
        $this->assertValues($values);
        $columns = array_keys($values);

        return 'insert into ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', array_map($this->quoteIdentifier(...), $columns)) . ')'
            . ' values (' . implode(', ', array_map(
                static fn (string $column): string => ':' . $column,
                $columns,
            )) . ')';
    }

    /**
     * @param array<string, mixed> $values
     */
    public function compileUpdate(string $table, array $values, ?string $where = null): string
    {
        $this->assertValues($values);

        $assignments = array_map(
            fn (string $column): string => $this->quoteIdentifier($column) . ' = :' . $column,
            array_keys($values),
        );

        $sql = 'update ' . $this->quoteIdentifier($table) . ' set ' . implode(', ', $assignments);

        if ($where !== null && trim($where) !== '') {
            $sql .= ' where ' . trim($where);
        }

        return $sql;
    }

    public function compileDelete(string $table, ?string $where = null): string
    {
        $sql = 'delete from ' . $this->quoteIdentifier($table);

        if ($where !== null && trim($where) !== '') {
            $sql .= ' where ' . trim($where);
        }

        return $sql;
    }

    public function compileCount(string $query): string
    {
        $query = $this->stripTrailingSemicolon($query);

        if ($query === '') {
            throw MssqlQueryException::forInvalidParameter('query', 'count query cannot be empty.');
        }

        return 'select count(*) as [aggregate] from (' . $query . ') as [_comphp_count]';
    }

    public function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw MssqlQueryException::forInvalidParameter('identifier', 'identifier cannot be empty.');
        }

        if ($identifier === '*') {
            return '*';
        }

        $segments = explode('.', $identifier);

        return implode('.', array_map(static function (string $segment): string {
            $segment = trim($segment);

            if ($segment === '') {
                throw MssqlQueryException::forInvalidParameter('identifier', 'identifier segments cannot be empty.');
            }

            if ($segment === '*') {
                return '*';
            }

            return '[' . str_replace(']', ']]', trim($segment, '[]')) . ']';
        }, $segments));
    }

    private function stripTrailingSemicolon(string $query): string
    {
        return rtrim(rtrim($query), ';');
    }

    private function assertLimitAndOffset(?int $limit, ?int $offset): void
    {
        if ($limit !== null && $limit < 0) {
            throw MssqlQueryException::forInvalidParameter('limit', 'limit cannot be negative.');
        }

        if ($offset !== null && $offset < 0) {
            throw MssqlQueryException::forInvalidParameter('offset', 'offset cannot be negative.');
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private function assertValues(array $values): void
    {
        if ($values === []) {
            throw MssqlQueryException::forInvalidParameter('values', 'values cannot be empty.');
        }

        foreach (array_keys($values) as $column) {
            if (!is_string($column) || trim($column) === '') {
                throw MssqlQueryException::forInvalidParameter('values', 'value keys must be non-empty column names.');
            }
        }
    }
}
