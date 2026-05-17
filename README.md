# CommonPHP Microsoft SQL Server Database Driver

Database driver for CommonPHP that builds queries for and connects to Microsoft SQL Server.

## Requirements

- PHP `^8.5`
- `comphp/database:^0.3`
- A SQL Server extension or connection library supported by the implementation

## Installation

Once this package is available through your Composer repositories, install it with:

```bash
composer require comphp/database-mssql
```

## Usage

```php
<?php

// TODO: Write usage
```

## Driver Notes

This driver is intended for applications that need Microsoft SQL Server connection and query behavior through CommonPHP Database.

The driver should keep SQL Server-specific connection strings, query behavior, parameter handling, and result behavior outside the core database package.

## Error Handling

Connection, query, transaction, configuration, and result failures should throw CommonPHP database driver exceptions instead of returning ambiguous false values.

## Documentation

- [Usage](docs/usage.md)
- [Testing](TESTING.md)
- [Contributing](CONTRIBUTING.md)
- [Security](SECURITY.md)

## License

MIT. See [LICENSE.md](LICENSE.md).
