# Bank Import CSV Parsers

A PHP-based suite of CSV parsers for banking and credit card statements (WMMC, BCR, ING, RBC), designed for integration into the `ksf_bank_import` system.

## Features

- **Multi-Bank Support**: Dedicated parsers for WMMC, BCR, ING, and RBC bank statement formats.
- **Generic CSV Engine**: Abstract `GenericCsvParser` with semi-auto column recognition via alias-based header mapping.
- **ContactData Integration**: Uses `ksfraser/contact-dto` for standardized payee/contact handling via `\Ksfraser\Contact\DTO\ContactData`.
- **Statement Management**: Groups transactions into statements automatically.
- **Standardized Output**: Returns `Statement` and `Transaction` entity objects for easy integration.
- **State Machine Processing**: Handles multi-line and split transactions (e.g., GnuCash style).
- **Requirement Traceability**: Follows [AGENTS.md](AGENTS.md) standards for documentation and mapping.

## Installation

```bash
composer require ksf-bank-import/csv-parsers
```

This will also install the `ksfraser/contact-dto` dependency automatically.

## Usage

```php
use Parsers\Parsers\WmmcCsvParser;
use Parsers\Parsers\BcrCsvParser;
use Parsers\Parsers\IngCsvParser;
use Parsers\Parsers\RbcCsvParser;

// Example: WMMC Parser
$parser = new WmmcCsvParser();
$statements = $parser->parse($csvContent);

// Each Statement contains Transactions with ContactData payees
foreach ($statements as $statement) {
    foreach ($statement->getTransactions() as $transaction) {
        $payee = $transaction->getPayee(); // Returns \Ksfraser\Contact\DTO\ContactData or null
        echo $payee->name;
    }
}
```

## Requirements

- PHP 7.3+
- Composer dependencies:
  - `ksfraser/contact-dto` (^0.1.0) — ContactData DTO for payee handling

## Architecture

This project uses a polymorphic parser hierarchy:

- **Base**: `src/Parsers/GenericCsvParser.php` — Abstract CSV engine with header detection, column mapping, and `mapToContactField()`.
- **Parsers**: `src/Parsers/` — Bank-specific implementations (WMMC, BCR, ING, RBC) extending the generic engine.
- **Entities**: `src/Entities/` — Domain objects: `Statement`, `Transaction`, `BankAccount`, `Balance`, `Currency`.
- **ContactData**: Payee data uses `\Ksfraser\Contact\DTO\ContactData` from the `ksfraser/contact-dto` package.

For detailed architecture, see [Project_Architecture_Blueprint.md](Project%20Docs/Project_Architecture_Blueprint.md).

## Testing

```bash
./vendor/bin/phpunit
```

- **62 tests**, 1080 assertions, 265 lines covered — **100% code coverage**
- **Test Organization**: `tests/Parsers/` (parser tests), `tests/Entities/` (entity tests), `tests/GlobalIntegrationTest.php` (integration)
- **Fixtures**: Anonymized test CSVs in `tests/fixtures/`

## Development

Follow the standards in [AGENTS.md](AGENTS.md) when contributing.
- **SOLID Principles**: Each parser has single responsibility; extend `GenericCsvParser` for new banks.
- **TDD**: Write tests before implementing features (Red-Green-Refactor).
- **Documentation**: Include PHPDoc with UML diagrams and `@requirement` tags.
