<?php

declare(strict_types=1);

namespace CommonPHP\Drivers\Database\MSSQL;

use CommonPHP\Drivers\Database\MSSQL\Exceptions\MssqlConnectionException;
use PDO;
use Throwable;

final readonly class MssqlConnectionFactory
{
    public function __construct(
        private MssqlDsnBuilder $dsnBuilder = new MssqlDsnBuilder(),
    ) {
    }

    /**
     * @param array<string, mixed>|MssqlConnectionOptions $options
     */
    public function connect(array|MssqlConnectionOptions $options): PDO
    {
        $options = is_array($options) ? MssqlConnectionOptions::fromArray($options) : $options;

        try {
            return new PDO(
                $this->dsnBuilder->build($options),
                $options->username(),
                $options->password(),
                $options->pdoAttributes(),
            );
        } catch (Throwable $throwable) {
            throw MssqlConnectionException::forConnection($options, $throwable);
        }
    }
}
