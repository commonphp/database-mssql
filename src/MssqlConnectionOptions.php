<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlConnectionException;
use PDO;

final readonly class MssqlConnectionOptions
{
    public const DEFAULT_USERNAME = 'sa';

    public const DEFAULT_PASSWORD = '';

    public const DEFAULT_SERVER = '127.0.0.1';

    public const DEFAULT_PORT = 1433;

    /**
     * @param array<int, mixed> $attributes
     */
    public function __construct(
        private string $username = self::DEFAULT_USERNAME,
        private string $password = self::DEFAULT_PASSWORD,
        private string $server = self::DEFAULT_SERVER,
        private string $database = '',
        private ?int $port = self::DEFAULT_PORT,
        private ?string $app = null,
        private bool $encrypt = false,
        private bool $trustServerCertificate = false,
        private ?int $loginTimeout = null,
        private bool $persistent = false,
        private bool $connectionPooling = true,
        private bool $multipleActiveResultSets = true,
        private ?string $charset = null,
        private array $attributes = [],
    ) {
        $this->assertValid();
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromArray(array $options): self
    {
        $attributes = self::option($options, 'attributes', 'driverOptions') ?? [];

        if (!is_array($attributes)) {
            throw MssqlConnectionException::forInvalidOptions('MSSQL PDO attributes must be an array.');
        }

        return new self(
            username: self::stringOption($options, self::DEFAULT_USERNAME, 'username'),
            password: self::stringOption($options, self::DEFAULT_PASSWORD, 'password'),
            server: self::stringOption($options, self::DEFAULT_SERVER, 'server', 'Server', 'host', 'Host'),
            database: self::stringOption($options, '', 'database', 'Database', 'dbname'),
            port: self::nullableIntOption($options, self::DEFAULT_PORT, 'port', 'Port'),
            app: self::nullableStringOption($options, 'app', 'App', 'applicationName', 'ApplicationName'),
            encrypt: self::boolOption($options, false, 'encrypt', 'Encrypt'),
            trustServerCertificate: self::boolOption(
                $options,
                false,
                'trustServerCertificate',
                'TrustServerCertificate',
                'trustServerCertificates',
                'TrustServerCertificates',
            ),
            loginTimeout: self::nullableIntOption($options, null, 'loginTimeout', 'LoginTimeout', 'timeout'),
            persistent: self::boolOption($options, false, 'persistent'),
            connectionPooling: self::boolOption($options, true, 'connectionPooling', 'ConnectionPooling'),
            multipleActiveResultSets: self::boolOption(
                $options,
                true,
                'multipleActiveResultSets',
                'MultipleActiveResultSets',
            ),
            charset: self::nullableStringOption($options, 'charset', 'CharacterSet'),
            attributes: $attributes,
        );
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function server(): string
    {
        return $this->server;
    }

    public function database(): string
    {
        return $this->database;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function app(): ?string
    {
        return $this->app;
    }

    public function encryptsConnection(): bool
    {
        return $this->encrypt;
    }

    public function trustsServerCertificate(): bool
    {
        return $this->trustServerCertificate;
    }

    public function loginTimeout(): ?int
    {
        return $this->loginTimeout;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    public function usesConnectionPooling(): bool
    {
        return $this->connectionPooling;
    }

    public function usesMultipleActiveResultSets(): bool
    {
        return $this->multipleActiveResultSets;
    }

    public function charset(): ?string
    {
        return $this->charset;
    }

    public function endpoint(): string
    {
        if (
            $this->port === null
            || str_contains($this->server, ',')
            || str_contains($this->server, '\\')
        ) {
            return $this->server;
        }

        return $this->server . ',' . $this->port;
    }

    /**
     * @return array<int, mixed>
     */
    public function pdoAttributes(): array
    {
        $attributes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($this->persistent) {
            $attributes[PDO::ATTR_PERSISTENT] = true;
        }

        return $this->attributes + $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'server' => $this->server,
            'database' => $this->database,
            'port' => $this->port,
            'app' => $this->app,
            'encrypt' => $this->encrypt,
            'trustServerCertificate' => $this->trustServerCertificate,
            'loginTimeout' => $this->loginTimeout,
            'persistent' => $this->persistent,
            'connectionPooling' => $this->connectionPooling,
            'multipleActiveResultSets' => $this->multipleActiveResultSets,
            'charset' => $this->charset,
            'attributes' => $this->attributes,
        ];
    }

    private function assertValid(): void
    {
        if (trim($this->username) === '') {
            throw MssqlConnectionException::forInvalidOptions('MSSQL username must be a non-empty string.');
        }

        if (trim($this->database) === '') {
            throw MssqlConnectionException::forInvalidOptions('MSSQL database name must be a non-empty string.');
        }

        if (trim($this->server) === '') {
            throw MssqlConnectionException::forInvalidOptions('MSSQL server must be a non-empty string.');
        }

        if ($this->port !== null && ($this->port < 1 || $this->port > 65535)) {
            throw MssqlConnectionException::forInvalidOptions('MSSQL port must be between 1 and 65535.');
        }

        if ($this->app !== null && trim($this->app) === '') {
            throw MssqlConnectionException::forInvalidOptions('MSSQL app name must be a non-empty string.');
        }

        if ($this->loginTimeout !== null && $this->loginTimeout < 1) {
            throw MssqlConnectionException::forInvalidOptions('MSSQL login timeout must be greater than zero.');
        }

        if ($this->charset !== null && trim($this->charset) === '') {
            throw MssqlConnectionException::forInvalidOptions('MSSQL character set must be a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function stringOption(array $options, string $default, string ...$keys): string
    {
        $value = self::option($options, ...$keys) ?? $default;

        if (!is_string($value)) {
            throw MssqlConnectionException::forInvalidOptions(
                'MSSQL option "' . $keys[0] . '" must be a string.',
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function nullableStringOption(array $options, string ...$keys): ?string
    {
        $value = self::option($options, ...$keys);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw MssqlConnectionException::forInvalidOptions(
                'MSSQL option "' . $keys[0] . '" must be a string.',
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function nullableIntOption(array $options, ?int $default, string ...$keys): ?int
    {
        $value = self::option($options, ...$keys);

        if ($value === null) {
            return $default;
        }

        if (!is_int($value)) {
            throw MssqlConnectionException::forInvalidOptions(
                'MSSQL option "' . $keys[0] . '" must be an integer.',
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function boolOption(array $options, bool $default, string ...$keys): bool
    {
        $value = self::option($options, ...$keys);

        if ($value === null) {
            return $default;
        }

        if (!is_bool($value)) {
            throw MssqlConnectionException::forInvalidOptions(
                'MSSQL option "' . $keys[0] . '" must be a boolean.',
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function option(array $options, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $options)) {
                return $options[$key];
            }
        }

        return null;
    }
}
