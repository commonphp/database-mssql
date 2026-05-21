<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Config\Contracts\AbstractConfigSchema;
use CommonPHP\Config\Exceptions\ConfigValidationException;

final class ConfigSchema extends AbstractConfigSchema
{
    /**
     * @var array<string, true>
     */
    private const ALLOWED_KEYS = [
        'username' => true,
        'password' => true,
        'server' => true,
        'Server' => true,
        'host' => true,
        'Host' => true,
        'database' => true,
        'Database' => true,
        'dbname' => true,
        'port' => true,
        'Port' => true,
        'app' => true,
        'App' => true,
        'applicationName' => true,
        'ApplicationName' => true,
        'encrypt' => true,
        'Encrypt' => true,
        'trustServerCertificate' => true,
        'TrustServerCertificate' => true,
        'trustServerCertificates' => true,
        'TrustServerCertificates' => true,
        'loginTimeout' => true,
        'LoginTimeout' => true,
        'timeout' => true,
        'persistent' => true,
        'connectionPooling' => true,
        'ConnectionPooling' => true,
        'multipleActiveResultSets' => true,
        'MultipleActiveResultSets' => true,
        'charset' => true,
        'CharacterSet' => true,
        'attributes' => true,
        'driverOptions' => true,
    ];

    public function __construct()
    {
        parent::__construct(self::defaultRules());
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        foreach (array_keys($config) as $key) {
            if (!isset(self::ALLOWED_KEYS[$key])) {
                $errors[] = 'Unsupported MSSQL connection configuration key: ' . $key . '.';
            }
        }

        if (!$this->hasAny($config, 'server', 'Server', 'host', 'Host')) {
            $errors[] = 'Missing required configuration key: server.';
        }

        if (!$this->hasAny($config, 'database', 'Database', 'dbname')) {
            $errors[] = 'Missing required configuration key: database.';
        }

        return $errors;
    }

    public function assertValid(array $config): void
    {
        $errors = $this->validate($config);

        if ($errors !== []) {
            throw new ConfigValidationException(
                'Configuration did not match schema: ' . implode(' ', $errors),
            );
        }
    }

    private static function nonEmptyString(mixed $value, string $field): bool|string
    {
        if (!is_string($value) || trim($value) === '') {
            return 'Configuration key ' . $field . ' must be a non-empty string.';
        }

        return true;
    }

    private static function validPort(mixed $value): bool|string
    {
        if (!is_int($value) || $value < 1 || $value > 65535) {
            return 'Configuration key port must be an integer between 1 and 65535.';
        }

        return true;
    }

    private static function positiveInteger(mixed $value, string $field): bool|string
    {
        if (!is_int($value) || $value < 1) {
            return 'Configuration key ' . $field . ' must be a positive integer.';
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function hasAny(array $config, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultRules(): array
    {
        $nonEmptyOptionalString = [
            'required' => false,
            'type' => 'string',
            'callback' => self::nonEmptyString(...),
        ];
        $positiveInteger = [
            'required' => false,
            'type' => 'integer',
            'callback' => self::positiveInteger(...),
        ];

        return [
            'username' => 'required|string',
            'password' => 'required|string',
            'server' => $nonEmptyOptionalString,
            'Server' => $nonEmptyOptionalString,
            'host' => $nonEmptyOptionalString,
            'Host' => $nonEmptyOptionalString,
            'database' => $nonEmptyOptionalString,
            'Database' => $nonEmptyOptionalString,
            'dbname' => $nonEmptyOptionalString,
            'port' => [
                'required' => false,
                'type' => 'integer',
                'callback' => self::validPort(...),
            ],
            'Port' => [
                'required' => false,
                'type' => 'integer',
                'callback' => self::validPort(...),
            ],
            'app' => $nonEmptyOptionalString,
            'App' => $nonEmptyOptionalString,
            'applicationName' => $nonEmptyOptionalString,
            'ApplicationName' => $nonEmptyOptionalString,
            'encrypt' => 'optional|boolean',
            'Encrypt' => 'optional|boolean',
            'trustServerCertificate' => 'optional|boolean',
            'TrustServerCertificate' => 'optional|boolean',
            'trustServerCertificates' => 'optional|boolean',
            'TrustServerCertificates' => 'optional|boolean',
            'loginTimeout' => $positiveInteger,
            'LoginTimeout' => $positiveInteger,
            'timeout' => $positiveInteger,
            'persistent' => 'optional|boolean',
            'connectionPooling' => 'optional|boolean',
            'ConnectionPooling' => 'optional|boolean',
            'multipleActiveResultSets' => 'optional|boolean',
            'MultipleActiveResultSets' => 'optional|boolean',
            'charset' => $nonEmptyOptionalString,
            'CharacterSet' => $nonEmptyOptionalString,
            'attributes' => 'optional|array',
            'driverOptions' => 'optional|array',
        ];
    }
}
