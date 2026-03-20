# WMMC CSV Parser

A PHP-based parser for Walmart MasterCard (WMMC) credit card statements, designed for integration into bank import systems.

## Features

- **Multi-format Support**: Handles various CSV header formats used by WMMC over the years.
- **Statement Management**: Groups transactions into daily statements automatically.
- **Standardized Output**: Returns internal `statement` and `transaction` objects for easy integration.
- **Requirement Traceability**: Follows [AGENTS.md](AGENTS.md) standards for documentation and mapping.

## Installation

This parser is a plugin for the `ksf_bank_import` system.

```bash
composer require ksfraser/ro-wmmc-csv-parser
```

## Usage

The parser is typically invoked through the core `bank_import` system, but can be used standalone:

```php
$parser = new ro_wmmc_csv_parser();
$statements = $parser->parse($content, [
    'bank_name' => 'WMMC',
    'account' => '************2251',
    'currency' => 'CAD'
]);
```

## Requirements

- PHP 7.3+
- Core `bank_import` module (provides `parser`, `statement`, and `transaction` base classes)

## Architecture

This project follows a plugin architecture:
- [parser.json](parser.json): Metadata defining the parser.
- [ro_wmmc_csv_parser.php](ro_wmmc_csv_parser.php): Core parsing logic.

For a detailed look at the architecture, see [Project_Architecture_Blueprint.md](Project_Architecture_Blueprint.md).

## Development

Follow the standards in [AGENTS.md](AGENTS.md) when contributing.
- **SOLID Principles**: Each class and method should have one responsibility.
- **TDD**: Write tests before implementing features.
- **Documentation**: Include PHPDoc with requirement mappings.
