# Product Requirements Document (PRD) - Bank Import CSV Parsers

## 1. Executive Summary
This project provides a robust, extensible suite of PHP-based CSV parsers for various banking and credit card statements (WMMC, ING, BCR, RBC). The architecture uses a generic CSV parsing engine (`GenericCsvParser`) with entity mapping to standardize data into structures required by the `ksf_bank_import` system, mirroring the patterns found in `ksf_ofxparser` and `ksf_qifparser`. Payee/contact data is handled via the external `ksfraser/contact-dto` package (`\Ksfraser\Contact\DTO\ContactData`).

## 2. User Stories
- **US-001**: As a system administrator, I want to easily add new bank parsers by creating a new PHP class extending `GenericCsvParser` and a JSON metadata file with minimal code.
- **US-002**: As a developer, I want to use a shared generic CSV entity mapper that handles column normalization across different formats.
- **US-003**: As a user, I want the system to automatically recognize columns in my CSV file even if the bank changes the order or labels (semi-auto column recognition).
- **US-004**: As a maintainer, I want the parsed data to map directly to `bi_statements` and `bi_transactions` entities used by the core import module.
- **US-005**: As a developer, I want payee data standardized via `\Ksfraser\Contact\DTO\ContactData` from the `ksfraser/contact-dto` package so it is consistent across all parsers and the broader ecosystem.

## 3. Scope
### In-Scope
- **Generic CSV Engine**: An abstract base parser (`GenericCsvParser`) that handles CSV iteration, header normalization, and state machine processing.
- **Entity Mapping System**: A mechanism to map CSV columns to `bi_statements` and `bi_transactions` properties, with `mapToContactField()` for payee data.
- **Semi-Auto Recognition**: Alias-based column mapping to "best-guess" column mapping based on common bank header aliases.
- **Bank-Specific Parsers**: Implementations for WMMC, BCR, ING, and RBC that extend the generic engine with minimal overrides.
- **Standardized Output**: Returns `Statement` and `Transaction` entity objects compatible with `ksf_bank_import`.
- **ContactData Integration**: Uses `\Ksfraser\Contact\DTO\ContactData` for all payee/contact entity mapping.

### Out-of-Scope
- Direct database persistence (handled by the core module).
- UI generation for file uploads (handled by the core module).

## 4. Technical Requirements
- **TR-001**: Implement a generic hook system as defined in [AGENTS.md](../AGENTS.md).
- **TR-002**: Use Strategy pattern for format-specific header mapping and detection via class hierarchy.
- **TR-003**: All classes must have UML diagrams in PHPDoc.
- **TR-004**: Entity objects must mirror properties in `bi_statements` and `bi_transactions`.
- **TR-005**: Use `\Ksfraser\Contact\DTO\ContactData` (from `ksfraser/contact-dto` ^0.1.0) for all payee/contact entity mapping instead of custom `Payee` class.

## 5. Risk Analysis
- **R-001**: Undocumented changes to CSV formats by banks (Mitigation: Use flexible header mapping and semi-auto detection).
- **R-002**: Ambiguous header names (e.g. "Date" vs "Posted Date") (Mitigation: Priority-based aliases).
- **R-003**: Performance issues with large CSV files (Mitigation: Efficient line-by-line processing).
- **R-004**: Breaking changes in `ksfraser/contact-dto` API (Mitigation: Pin to `^0.1.0`, test ContactData serialization round-trips).
