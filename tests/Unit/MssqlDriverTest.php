<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL\Tests\Unit;

use CommonPHP\Config\Exceptions\ConfigValidationException;
use CommonPHP\Database\Enums\FetchMode;
use CommonPHP\Database\Exceptions\TransactionException;
use CommonPHP\Drivers\Database\MSSQL\ConfigSchema;
use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlConnectionException;
use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlQueryException;
use CommonPHP\Drivers\Database\MSSQL\MssqlConnectionOptions;
use CommonPHP\Drivers\Database\MSSQL\MssqlDatabaseDriver;
use CommonPHP\Drivers\Database\MSSQL\MssqlDsnBuilder;
use CommonPHP\Drivers\Database\MSSQL\MssqlQueryCompiler;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MssqlDriverTest extends TestCase
{
    public function testConnectionOptionsAndDsnBuilderExposeMssqlDefaults(): void
    {
        $options = new MssqlConnectionOptions(
            username: 'app',
            password: 'secret',
            server: 'sql.internal',
            database: 'app_db',
            port: 1444,
            app: 'kt-app',
            encrypt: true,
            trustServerCertificate: true,
            loginTimeout: 5,
            charset: 'UTF-8',
        );

        self::assertSame('app', $options->username());
        self::assertSame('secret', $options->password());
        self::assertSame('sql.internal,1444', $options->endpoint());
        self::assertSame(
            'sqlsrv:Server=sql.internal,1444;Database=app_db;Encrypt=1;TrustServerCertificate=1;ConnectionPooling=1;MultipleActiveResultSets=1;APP=kt-app;LoginTimeout=5;CharacterSet=UTF-8',
            (new MssqlDsnBuilder())->build($options),
        );
        self::assertSame(PDO::ERRMODE_EXCEPTION, $options->pdoAttributes()[PDO::ATTR_ERRMODE]);
    }

    public function testConnectionOptionsRejectInvalidConfiguration(): void
    {
        $this->expectException(MssqlConnectionException::class);

        new MssqlConnectionOptions(database: '', port: 0);
    }

    public function testConfigSchemaMatchesDriverConnectionShape(): void
    {
        $schema = new ConfigSchema();
        $valid = [
            'username' => 'sa',
            'password' => '',
            'Server' => '127.0.0.1',
            'Database' => 'app',
            'Encrypt' => false,
            'TrustServerCertificate' => true,
        ];

        self::assertSame([], $schema->validate($valid));

        $errors = $schema->validate($valid + ['extra' => true]);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('Unsupported MSSQL connection configuration key: extra.', implode(' ', $errors));

        $this->expectException(ConfigValidationException::class);
        $schema->assertValid(['username' => 'sa', 'password' => '']);
    }

    public function testQueryCompilerBuildsSimpleMssqlStatements(): void
    {
        $compiler = new MssqlQueryCompiler();

        self::assertSame('[users].[name]', $compiler->quoteIdentifier('users.name'));
        self::assertSame(
            'select top (10) [id], [name] from [users] where active = :active',
            $compiler->compileSelect('users', ['id', 'name'], 'active = :active', 10),
        );
        self::assertSame(
            'select [id], [name] from [users] where active = :active order by (select 0) offset 5 rows fetch next 10 rows only',
            $compiler->compileSelect('users', ['id', 'name'], 'active = :active', 10, 5),
        );
        self::assertSame(
            'insert into [users] ([name], [active]) values (:name, :active)',
            $compiler->compileInsert('users', ['name' => 'Ada', 'active' => true]),
        );
        self::assertSame(
            'update [users] set [name] = :name where id = :id',
            $compiler->compileUpdate('users', ['name' => 'Ada'], 'id = :id'),
        );
        self::assertSame(
            'select count(*) as [aggregate] from (select * from users) as [_comphp_count]',
            $compiler->compileCount('select * from users;'),
        );
    }

    public function testDriverExecutesQueriesFetchesRowsAndCountsResults(): void
    {
        $driver = $this->driver();

        self::assertSame(1, $driver->execute(
            'insert into users (name, active) values (:name, :active)',
            ['name' => 'Ada', 'active' => true],
        ));
        self::assertSame('1', $driver->lastInsertId());
        self::assertSame('Ada', $driver->fetchScalar('select name from users where id = ?', [1]));
        self::assertSame('missing', $driver->fetchScalar('select name from users where id = :id', ['id' => 99], 'missing'));
        self::assertSame(['id' => 1, 'name' => 'Ada', 'active' => 1], $driver->fetchOne('select * from users where id = :id', ['id' => 1]));
        self::assertCount(1, $driver->fetchAll('select * from users', fetchMode: FetchMode::FETCH_OBJ));
        self::assertSame(1, $driver->count('select * from users where active = :active', ['active' => true]));
        self::assertTrue($driver->ping());
    }

    public function testDriverTransactionsCommitAndRollBack(): void
    {
        $driver = $this->driver();

        $driver->transaction(static function (MssqlDatabaseDriver $connection): void {
            $connection->execute('insert into users (name, active) values (:name, :active)', [
                'name' => 'Grace',
                'active' => true,
            ]);
        });

        self::assertSame(1, $driver->count('select * from users'));

        try {
            $driver->transaction(static function (MssqlDatabaseDriver $connection): void {
                $connection->execute('insert into users (name, active) values (:name, :active)', [
                    'name' => 'Rollback',
                    'active' => true,
                ]);

                throw new RuntimeException('stop');
            });
        } catch (TransactionException) {
        }

        self::assertSame(1, $driver->count('select * from users'));
    }

    public function testDriverWrapsQueryFailures(): void
    {
        $this->expectException(MssqlQueryException::class);

        $this->driver()->fetchAll('select * from missing_table');
    }

    private function driver(): MssqlDatabaseDriver
    {
        return new MssqlDatabaseDriver(new FakeMssqlPdo());
    }
}

final class FakeMssqlPdo extends PDO
{
    /**
     * @var list<array{id: int, name: string, active: int}>
     */
    private array $users = [];

    /**
     * @var array{users: list<array{id: int, name: string, active: int}>, nextId: int}|null
     */
    private ?array $transactionSnapshot = null;

    private int $nextId = 1;

    private string|false $lastInsertId = false;

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new FakeMssqlStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = new FakeMssqlStatement($this, $query);
        $statement->execute();

        return $statement;
    }

    public function beginTransaction(): bool
    {
        $this->transactionSnapshot = [
            'users' => $this->users,
            'nextId' => $this->nextId,
        ];

        return true;
    }

    public function commit(): bool
    {
        $this->transactionSnapshot = null;

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactionSnapshot !== null) {
            $this->users = $this->transactionSnapshot['users'];
            $this->nextId = $this->transactionSnapshot['nextId'];
            $this->transactionSnapshot = null;
        }

        return true;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    /**
     * @param array<string|int, mixed> $bindings
     * @return array{rows: list<array<string, mixed>>, affectedRows: int}
     */
    public function executePrepared(string $query, array $bindings): array
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $query) ?? $query));

        if ($normalized === 'select 1') {
            return ['rows' => [['value' => 1]], 'affectedRows' => 0];
        }

        if ($normalized === 'select * from missing_table') {
            throw new PDOException('table does not exist');
        }

        if ($normalized === 'insert into users (name, active) values (:name, :active)') {
            $id = $this->nextId++;
            $this->lastInsertId = (string) $id;
            $this->users[] = [
                'id' => $id,
                'name' => (string) $bindings[':name'],
                'active' => (bool) $bindings[':active'] ? 1 : 0,
            ];

            return ['rows' => [], 'affectedRows' => 1];
        }

        if (preg_match('/^select count\(\*\) as \[aggregate\] from \((.+)\) as \[_comphp_count\]$/', $normalized, $matches) === 1) {
            return [
                'rows' => [['aggregate' => count($this->selectRows($matches[1], $bindings))]],
                'affectedRows' => 0,
            ];
        }

        return ['rows' => $this->selectRows($normalized, $bindings), 'affectedRows' => 0];
    }

    /**
     * @param array<string|int, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    private function selectRows(string $query, array $bindings): array
    {
        if ($query === 'select * from users') {
            return $this->users;
        }

        if ($query === 'select * from users where active = :active') {
            $active = (bool) $bindings[':active'];

            return array_values(array_filter(
                $this->users,
                static fn (array $row): bool => (bool) $row['active'] === $active,
            ));
        }

        if ($query === 'select * from users where id = :id') {
            return $this->rowsForId((int) $bindings[':id']);
        }

        if ($query === 'select name from users where id = :id') {
            return array_map(
                static fn (array $row): array => ['name' => $row['name']],
                $this->rowsForId((int) $bindings[':id']),
            );
        }

        if ($query === 'select name from users where id = ?') {
            return array_map(
                static fn (array $row): array => ['name' => $row['name']],
                $this->rowsForId((int) $bindings[1]),
            );
        }

        throw new PDOException('Unsupported fake query: ' . $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsForId(int $id): array
    {
        return array_values(array_filter(
            $this->users,
            static fn (array $row): bool => $row['id'] === $id,
        ));
    }
}

final class FakeMssqlStatement extends PDOStatement
{
    /**
     * @var array<string|int, mixed>
     */
    private array $bindings = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $rows = [];

    private int $affectedRows = 0;

    public function __construct(
        private readonly FakeMssqlPdo $pdo,
        private readonly string $query,
    ) {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[$param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            $this->bindings = $params;
        }

        $result = $this->pdo->executePrepared($this->query, $this->bindings);
        $this->rows = $result['rows'];
        $this->affectedRows = $result['affectedRows'];

        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        $row = array_shift($this->rows);

        return $row === null ? false : $this->mapRow($row, $mode);
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return array_map(fn (array $row): mixed => $this->mapRow($row, $mode), $this->rows);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->rows);

        if ($row === null) {
            return false;
        }

        return array_values($row)[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->affectedRows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row, int $mode): mixed
    {
        return match ($mode) {
            PDO::FETCH_NUM => array_values($row),
            PDO::FETCH_BOTH => array_values($row) + $row,
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_COLUMN => array_values($row)[0] ?? null,
            default => $row,
        };
    }
}
