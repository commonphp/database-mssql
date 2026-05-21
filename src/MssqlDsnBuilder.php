<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

final class MssqlDsnBuilder
{
    public function build(MssqlConnectionOptions $options): string
    {
        $parts = [
            'Server' => $options->endpoint(),
            'Database' => $options->database(),
            'Encrypt' => $this->booleanValue($options->encryptsConnection()),
            'TrustServerCertificate' => $this->booleanValue($options->trustsServerCertificate()),
            'ConnectionPooling' => $this->booleanValue($options->usesConnectionPooling()),
            'MultipleActiveResultSets' => $this->booleanValue($options->usesMultipleActiveResultSets()),
        ];

        if ($options->app() !== null) {
            $parts['APP'] = $options->app();
        }

        if ($options->loginTimeout() !== null) {
            $parts['LoginTimeout'] = (string) $options->loginTimeout();
        }

        if ($options->charset() !== null) {
            $parts['CharacterSet'] = $options->charset();
        }

        return 'sqlsrv:' . implode(';', array_map(
            static fn (string $key, string $value): string => $key . '=' . $value,
            array_keys($parts),
            $parts,
        ));
    }

    private function booleanValue(bool $value): string
    {
        return $value ? '1' : '0';
    }
}
