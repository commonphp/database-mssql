<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Database\Contracts\AbstractDatabaseDriver;
use CommonPHP\Database\Enums\FetchMode;
use CommonPHP\Database\Exceptions\DatabaseException;
use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlDatabaseDriverException;
use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlQueryException;
use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlTransactionException;
use PDO;
use PDOStatement;
use Throwable;

final class MssqlDatabaseDriver extends AbstractDatabaseDriver
{
    private ?PDO $pdo = null;

    private ?MssqlConnectionOptions $options = null;

    /**
     * @param array<string, mixed>|MssqlConnectionOptions|PDO|null $connection
     * @param array<int, mixed> $attributes
     */
    public function __construct(
        array|MssqlConnectionOptions|PDO|null $connection = null,
        string $username = MssqlConnectionOptions::DEFAULT_USERNAME,
        string $password = MssqlConnectionOptions::DEFAULT_PASSWORD,
        string $server = MssqlConnectionOptions::DEFAULT_SERVER,
        string $database = '',
        ?int $port = MssqlConnectionOptions::DEFAULT_PORT,
        ?string $app = null,
        bool $encrypt = false,
        bool $trustServerCertificate = false,
        ?int $loginTimeout = null,
        bool $persistent = false,
        bool $connectionPooling = true,
        bool $multipleActiveResultSets = true,
        ?string $charset = null,
        array $attributes = [],
        private readonly MssqlConnectionFactory $connectionFactory = new MssqlConnectionFactory(),
        private readonly MssqlStatementBinder $statementBinder = new MssqlStatementBinder(),
        private readonly MssqlResultMapper $resultMapper = new MssqlResultMapper(),
        private readonly MssqlQueryCompiler $queryCompiler = new MssqlQueryCompiler(),
    ) {
        if ($connection instanceof PDO) {
            $this->pdo = $connection;

            return;
        }

        $this->options = match (true) {
            $connection instanceof MssqlConnectionOptions => $connection,
            is_array($connection) => MssqlConnectionOptions::fromArray($connection),
            default => new MssqlConnectionOptions(
                username: $username,
                password: $password,
                server: $server,
                database: $database,
                port: $port,
                app: $app,
                encrypt: $encrypt,
                trustServerCertificate: $trustServerCertificate,
                loginTimeout: $loginTimeout,
                persistent: $persistent,
                connectionPooling: $connectionPooling,
                multipleActiveResultSets: $multipleActiveResultSets,
                charset: $charset,
                attributes: $attributes,
            ),
        };
    }

    public function getName(): string
    {
        return 'mssql';
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if ($this->options === null) {
            throw new MssqlDatabaseDriverException('MSSQL connection options are not configured.');
        }

        return $this->pdo = $this->connectionFactory->connect($this->options);
    }

    public function queryCompiler(): MssqlQueryCompiler
    {
        return $this->queryCompiler;
    }

    public function count(string $query, array $parameters = []): int
    {
        $statement = $this->runStatement('count', $this->queryCompiler->compileCount($query), $parameters);

        return (int) $this->resultMapper->fetchScalar($statement, 0);
    }

    public function execute(string $query, array $parameters = []): int|bool
    {
        return $this->runStatement('execute', $query, $parameters)->rowCount();
    }

    public function fetchScalar(string $query, array $parameters = [], mixed $default = null): mixed
    {
        return $this->resultMapper->fetchScalar(
            $this->runStatement('fetch scalar', $query, $parameters),
            $default,
        );
    }

    public function fetchOne(string $query, array $parameters = []): array|false
    {
        return $this->resultMapper->fetchOne($this->runStatement('fetch one', $query, $parameters));
    }

    public function fetchAll(
        string $query,
        array $parameters = [],
        FetchMode $fetchMode = FetchMode::FETCH_ASSOC,
    ): array {
        return $this->resultMapper->fetchAll(
            $this->runStatement('fetch all', $query, $parameters),
            $fetchMode,
        );
    }

    public function beginTransaction(): void
    {
        try {
            if (!$this->pdo()->beginTransaction()) {
                throw MssqlTransactionException::forFailure('begin');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw MssqlTransactionException::forOperation('begin', $throwable);
        }
    }

    public function commit(): void
    {
        try {
            if (!$this->pdo()->commit()) {
                throw MssqlTransactionException::forFailure('commit');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw MssqlTransactionException::forOperation('commit', $throwable);
        }
    }

    public function rollBack(): void
    {
        try {
            if (!$this->pdo()->rollBack()) {
                throw MssqlTransactionException::forFailure('roll back');
            }
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw MssqlTransactionException::forOperation('roll back', $throwable);
        }
    }

    public function lastInsertId(): string|false
    {
        try {
            return $this->pdo()->lastInsertId();
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw MssqlDatabaseDriverException::forOperation('last insert id', $throwable);
        }
    }

    public function ping(): bool
    {
        try {
            $this->pdo()->query('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    private function runStatement(string $operation, string $query, array $parameters = []): PDOStatement
    {
        try {
            $statement = $this->prepareStatement($query);
            $this->statementBinder->bind($statement, $parameters, $query);
            $statement->execute();

            return $statement;
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw MssqlQueryException::forOperation($operation, $query, $throwable);
        }
    }

    private function prepareStatement(string $query): PDOStatement
    {
        $statement = $this->pdo()->prepare($query);

        if (!$statement instanceof PDOStatement) {
            throw MssqlQueryException::forPrepareFailure($query);
        }

        return $statement;
    }
}
