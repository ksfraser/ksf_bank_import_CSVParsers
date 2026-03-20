# Project Architecture Blueprint: Bank Import CSV Parsers

## Overview
This project provides a PHP-based suite of CSV parsers for various banking and credit card statements (WMMC, BCR, ING, RBC, Manulife). It is designed to be integrated into the `ksf_bank_import` system, following the standards defined in [AGENTS.md](../AGENTS.md). The architecture uses a polymorphic parser hierarchy with a shared generic engine and standardized entity output.

## Technology Stack
- **Language**: PHP 7.3+
- **Data Format**: CSV (input), JSON (metadata)
- **Architecture Pattern**: Layered Architecture (Abstract Parser + Strategy Pattern)
- **Testing**: PHPUnit 9.5 (62 tests, 1080 assertions, 100% code coverage)
- **Dependencies**: `ksfraser/contact-dto` (^0.1.0) for standardized contact/payee DTO handling
- **Autoloading**: PSR-4 (`Parsers\` → `src/`)

## Core Components

### 1. Metadata: `parser.json`
Defines the parser's identity, namespace, and configuration for the core bank import system.

### 2. Abstract Base Parser: `src/Parsers/GenericCsvParser.php`
The foundation for all bank-specific parsers. Provides:
- **Namespace**: `Parsers\Parsers`
- **Class**: `GenericCsvParser` (abstract)
- **Key Methods**:
    - `parse()`: Abstract entry point for CSV parsing.
    - `detectColumnHeaders()`: Header detection and normalization via alias maps.
    - `mapColumnToField()`: Maps CSV column names to entity field names.
    - `mapToContactField()`: Maps CSV property names to `ContactData` fields (e.g., `state` → `state_province`, `postalCode` → `postal_code`, `address1` → `address_line_1`).
    - `processRow()`: Processes each CSV row, building `Transaction` entities with `ContactData` payees.

### 3. Bank-Specific Parsers
Each extends `GenericCsvParser` with minimal bank-specific overrides:

| Parser | File | Bank/Format |
|--------|------|-------------|
| `WmmcCsvParser` | `src/Parsers/WmmcCsvParser.php` | Walmart Mastercard (CAD) |
| `BcrCsvParser` | `src/Parsers/BcrCsvParser.php` | BCR Romania (RON) |
| `IngCsvParser` | `src/Parsers/IngCsvParser.php` | ING Romania (RON) |
| `RbcCsvParser` | `src/Parsers/RbcCsvParser.php` | RBC Canada (CAD) |

### 4. Entity Classes
OFX-aligned domain objects in `Parsers\Entities` namespace:

| Entity | File | Purpose |
|--------|------|---------|
| `Statement` | `src/Entities/Statement.php` | Represents a parsed bank statement (maps to `bi_statements`) |
| `Transaction` | `src/Entities/Transaction.php` | Individual transaction with `ContactData` payee (maps to `bi_transactions`) |
| `BankAccount` | `src/Entities/BankAccount.php` | Bank account metadata |
| `Balance` | `src/Entities/Balance.php` | Statement balance information |
| `Currency` | `src/Entities/Currency.php` | Currency handling |
| `Payee` | `src/Entities/Payee.php` | **DEPRECATED** — Stub extending `\Ksfraser\Contact\DTO\ContactData` |

### 5. External Dependency: ContactData
- **Package**: `ksfraser/contact-dto` (v0.1.0)
- **Class**: `\Ksfraser\Contact\DTO\ContactData`
- **Usage**: Replaces the legacy `Payee` entity for all payee/contact data handling.
- `Transaction::setPayee(ContactData $contact)` serializes via `json_encode($contact->toArray())`
- `Transaction::getPayee(): ?ContactData` deserializes via `ContactData::fromArray(json_decode(...))`

## Architectural Patterns

### Module Architecture
The project follows an abstract parser hierarchy where `GenericCsvParser` provides the shared CSV engine and each bank parser extends it with format-specific configuration. This replaces the earlier monolithic `ro_wmmc_csv_parser.php` approach.

### Data Flow
1. **Input**: Raw CSV content (bank-specific format).
2. **Parsing**:
    - Header detection via alias-based column mapping.
    - State machine for multi-line/split transaction support.
    - Row-by-row processing building `Transaction` entities.
    - Payee data mapped to `ContactData` via `mapToContactField()`.
3. **Output**: An array of `Statement` objects, each containing `Transaction` objects with `ContactData` payees.

### Class Hierarchy
```
GenericCsvParser (abstract)
├── WmmcCsvParser
├── BcrCsvParser
├── IngCsvParser
└── RbcCsvParser

ContactData (from ksfraser/contact-dto)
└── Payee (deprecated stub)
```

## Compliance with AGENTS.md
The current implementation aligns with the following standards:
- **SOLID Principles**: Each parser has single responsibility; `GenericCsvParser` provides base mechanics; specific banks extend with minimal overrides.
- **Polymorphism over Conditionals**: Bank-specific logic implemented via class hierarchy, not conditionals.
- **Type Hinting**: Strict typing throughout all methods and return types.
- **Documentation**: All classes include PHPDoc with UML diagrams and `@requirement` tags.
- **Testing**: 62 unit tests with 100% code coverage (1080 assertions, 265 lines covered).
- **ContactData Integration**: Payee data standardized via `ksfraser/contact-dto` package.
- **Dependency Injection**: External dependencies injected via constructor or method parameters.

## Extension Points
- **New Bank Formats**: Create a new class extending `GenericCsvParser` with bank-specific header aliases and column mappings.
- **Additional Data Fields**: Entity classes can be extended with additional properties as required by the core system.
- **ContactData Fields**: The `mapToContactField()` method can be extended to map additional CSV fields to ContactData properties.
