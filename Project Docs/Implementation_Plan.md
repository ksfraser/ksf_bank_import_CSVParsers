# Implementation Plan: Bank Import CSV Parsers

## Purpose
Refactor existing parsers (`ro_wmmc_csv_parser`, `ro_bcr_csv_parser`, `ro_ing_csv_parser`) and implement new parsing logic according to the requirements in [AGENTS.md](../AGENTS.md) and [PRD.md](PRD.md).

## Phase 0: Infrastructure & State Machine ✅
### Task 0.1: Define Base Entities ✅
- **Description**: Create `Statement`, `Transaction`, `BankAccount`, `Balance`, and `Currency` entity classes in `src/Entities/` that mirror `bi_statements` and `bi_transactions` properties from `ksf_bank_import`.
- **Goal**: Standardized data structures across all bank formats.
- **Result**: All entity classes implemented with OFX-aligned properties.

### Task 0.2: Implement `GenericCsvParser` with State Machine ✅
- **Description**: Built a base abstract parser (`src/Parsers/GenericCsvParser.php`) that supports:
    - **Semi-auto column recognition**: Alias-based mapping for `valueTimestamp`, `amount`, `merchant`, etc.
    - **Split Transaction Support**: A state machine that can accumulate "split" or "multi-line" data into a single `Transaction` entity.
- **Goal**: FR-001, FR-004.

### Task 0.3: ContactData Integration ✅
- **Description**: Migrate all payee handling from custom `Payee` class to `\Ksfraser\Contact\DTO\ContactData` from `ksfraser/contact-dto` package (v0.1.0).
- **Tasks Completed**:
    - Updated `Transaction::setPayee()` / `getPayee()` to use `ContactData` (serialized via `json_encode(toArray())`).
    - Added `mapToContactField()` method in `GenericCsvParser` for CSV-to-ContactData field mapping (`state` → `state_province`, `postalCode` → `postal_code`, `address1` → `address_line_1`).
    - Deprecated `Payee.php` as a stub extending `ContactData` with `@codeCoverageIgnore`.
- **Goal**: FR-002, TR-005, TR-006.

## Phase 1: Core Refactoring (WMMC, BCR, ING, RBC) ✅
### Task 1.1: Refactor WMMC Parser ✅
- **Description**: Converted `ro_wmmc_csv_parser` to `WmmcCsvParser` extending `GenericCsvParser` with bank-specific header aliases.
- **Goal**: SRP and reduced cognitive complexity.

### Task 1.2: Implement BCR and ING Parsers ✅
- **Description**: Implemented `BcrCsvParser` and `IngCsvParser` using the same generic foundation.

### Task 1.3: Implement RBC Parser ✅
- **Description**: Implemented `RbcCsvParser` for RBC Canada CSV format (HISA, savings accounts).

## Phase 2: Test Fixtures and Validation ✅
### Task 2.1: Establish Test Fixtures ✅
- **Description**: Copied example CSVs from `CSVs/` to `tests/fixtures/` and created anonymized versions. Sensitive data sanitized:
    - Account numbers replaced with `9999999` / `8888888` in filenames and content.
    - Real bank names replaced with `BANK1` / `BANK2`.
    - Three corrupted fixture files re-sanitized with proper CSV structure after bad sanitization script destroyed dates/amounts.
- **Goal**: Reproducible testing with anonymized real-world data.

### Task 2.2: Unit Testing (Red-Green-Refactor) ✅
- **Description**: Created unit tests for each bank parser, entity classes, and integration tests.
- **Result**: **62 tests, 1080 assertions, 265/265 lines covered (100% code coverage)**.
- **Test Files**:
    - `tests/Parsers/GenericCsvParserTest.php`
    - `tests/Parsers/WmmcCsvParserTest.php`
    - `tests/Parsers/BcrCsvParserTest.php`
    - `tests/Parsers/IngCsvParserTest.php`
    - `tests/Parsers/RbcCsvParserTest.php`
    - `tests/Entities/EntityTest.php`
    - `tests/Entities/OfxEntityTest.php`
    - `tests/GlobalIntegrationTest.php`

## Phase 3: Documentation and Audit
### Task 3.1: Add PHPDoc and UML ✅
- **Description**: Added comprehensive PHPDoc blocks with UML diagrams and requirement mappings (`@requirement`).
- **Goal**: [AGENTS.md](../AGENTS.md) compliance.

### Task 3.2: Final Traceability Review ✅
- **Description**: Updated [RTM.md](RTM.md) with all current file paths, function names, and test references. Verified all FR/TR goals are met.
