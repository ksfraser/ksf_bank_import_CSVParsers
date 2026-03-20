# Implementation Plan: Bank Import CSV Parsers

## Purpose
Refactor existing parsers (`ro_wmmc_csv_parser`, `ro_bcr_csv_parser`, `ro_ing_csv_parser`) and implement new parsing logic according to the requirements in [AGENTS.md](AGENTS.md) and [PRD.md](Project Docs/PRD.md).

## Phase 0: Infrastructure & State Machine
### Task 0.1: Define Base Entities
- **Description**: Create `Statement` and `Transaction` entity classes that mirror `bi_statements` and `bi_transactions` properties from `ksf_bank_import`.
- **Goal**: Standardized data structures across all bank formats.

### Task 0.2: Implement `GenericCsvParser` with State Machine
- **Description**: Build a base parser that supports:
    - **Semi-auto column recognition**: Alias-based mapping for `valueTimestamp`, `amount`, `merchant`, etc.
    - **Split Transaction Support**: A state machine that can accumulate "split" or "multi-line" data (e.g. Gnucash style) into a single `Transaction` entity.
- **Goal**: FR-001, FR-004.

## Phase 1: Core Refactoring (WMMC, BCR, ING)
### Task 1.1: Refactor WMMC Parser
- **Description**: Convert `ro_wmmc_csv_parser` to extend `GenericCsvParser` and use the alias mapping engine.
- **Goal**: SRP and reduce cognitive complexity.

### Task 1.2: Implement BCR and ING Parsers
- **Description**: Complete the implementations for BCR and ING using the same generic foundation.

## Phase 2: Test Fixtures and Validation
### Task 2.1: Establish Test Fixtures
- **Description**: Copy example CSVs from `CSVs/` to `tests/fixtures/` and create anonymized versions where necessary (following the pattern in `ksf_ofxparser`).
- **Goal**: Reproducible testing with real-world data.

### Task 2.2: Unit Testing (Red-Green-Refactor)
- **Description**: Create unit tests for each bank parser, ensuring the state machine correctly handles both single-line and split transactions.
- **Goal**: 100% coverage and requirement verification.

## Phase 3: Documentation and Audit
### Task 3.1: Add PHPDoc and UML
- **Description**: Add comprehensive PHPDoc blocks with UML diagrams and requirement mappings (@requirement).
- **Goal**: [AGENTS.md](AGENTS.md) compliance.

### Task 3.2: Final Traceability Review
- **Description**: Update [RTM.md](Project Docs/RTM.md) and verify all FR/TR goals are met.
