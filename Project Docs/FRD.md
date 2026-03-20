# Functional Requirements Document (FRD) - Bank Import CSV Parsers

## 1. Requirement: Semi-Auto Column Recognition (FR-001)
- The generic parser must use an alias mapping system to detect common columns (e.g., "Date", "T-Date", "Trans Date" -> `valueTimestamp`).
- Support for regular expressions in header matching is a plus.
- Manual overrides for specific bank parsers must be supported.

## 2. Requirement: Entity Mapping (FR-002)
- Parsed data must be stored in standardized `bi_statements` and `bi_transactions` structure.
- Implementation must use Entity classes mimicking the properties in `ksf_bank_import/class.bi_statements.php` and `ksf_bank_import/class.bi_transactions.php`.

## 3. Requirement: State-Machine Transaction Processing (FR-003)
- Parsers must handle multiple row types (e.g., single-line vs multi-line transactions).
- For WMMC, handle "TRF" (Transactions) vs "COM" (Fees/Commissions).

## 4. Requirement: Generic CSV Engine (FR-004)
- A base `GenericCsvParser` class should handle the mechanics of file reading, line splitting, and field extraction.
- Sub-classes for specific banks (WMMC, BCR, ING) should only override configuration and specialized mapping logic.

## 5. Requirement: Output Compatibility (FR-005)
- Output must be an array of `statement` objects compatible with existing `ksf_bank_import` controller expectations.
- All extracted amounts must be normalized as floating-point numbers.
- "TransactionDC" must be calculated based on the presence of negative signs or merchant flags (PAYMENT/REFUND).
