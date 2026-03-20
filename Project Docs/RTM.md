# Requirements Traceability Matrix (RTM)

> **Test Summary**: 62 tests, 1080 assertions, 265/265 lines covered (100% code coverage)

| Requirement ID | Description | File(s) | Function(s) | Test(s) |
| --- | --- | --- | --- | --- |
| PRD-US-001 | Add new bank parsers via metadata | `parser.json`, `src/Parsers/` | Bank-specific parser classes extend `GenericCsvParser` | `tests/Parsers/WmmcCsvParserTest.php`, `tests/Parsers/BcrCsvParserTest.php`, `tests/Parsers/IngCsvParserTest.php`, `tests/Parsers/RbcCsvParserTest.php` |
| PRD-US-002 | Follow SOLID and coding standards | `src/Parsers/GenericCsvParser.php`, `src/Parsers/WmmcCsvParser.php`, `src/Parsers/BcrCsvParser.php`, `src/Parsers/IngCsvParser.php`, `src/Parsers/RbcCsvParser.php` | All parser and entity classes | `tests/Parsers/GenericCsvParserTest.php`, all parser tests |
| PRD-US-003 | Accurate parsing across formats | `src/Parsers/` (all parsers) | `parse()` per parser | All parser tests, `tests/GlobalIntegrationTest.php` |
| FR-001 | Semi-Auto Column Recognition | `src/Parsers/GenericCsvParser.php` | `mapColumnToField()`, `detectColumnHeaders()` | `tests/Parsers/GenericCsvParserTest.php` |
| FR-002 | Entity Mapping (ContactData) | `src/Entities/Transaction.php`, `src/Parsers/GenericCsvParser.php` | `mapToContactField()`, `setPayee()`, `getPayee()` | `tests/Entities/OfxEntityTest.php`, `tests/Entities/EntityTest.php` |
| FR-003 | State-Machine Transaction Processing | `src/Parsers/GenericCsvParser.php` | `parse()` with state machine logic | `tests/GlobalIntegrationTest.php`, `tests/Parsers/GenericCsvParserTest.php` |
| FR-004 | Generic CSV Engine | `src/Parsers/GenericCsvParser.php` | Base parsing mechanics | `tests/Parsers/GenericCsvParserTest.php` |
| FR-005 | Output Compatibility (Statement/Transaction + ContactData) | `src/Entities/Statement.php`, `src/Entities/Transaction.php` | Property getters/setters, `toArray()` | `tests/Entities/OfxEntityTest.php`, `tests/Entities/EntityTest.php` |
| FR-007 | Extensibility | `parser.json`, `src/Parsers/GenericCsvParser.php` | Abstract parser + bank-specific subclasses | All parser tests |
| TR-001 | Hook System | `AGENTS.md` | N/A | N/A |
| TR-002 | Strategy Pattern for Headers | `src/Parsers/WmmcCsvParser.php`, `src/Parsers/BcrCsvParser.php`, `src/Parsers/IngCsvParser.php`, `src/Parsers/RbcCsvParser.php` | Bank-specific header alias maps | All parser tests |
| TR-003 | UML in PHPDoc | `src/Parsers/`, `src/Entities/` | Class DocBlocks | N/A |
| TR-004 | Composer Management | `composer.json` | N/A | N/A |
| TR-005 | ContactData Integration | `src/Entities/Transaction.php`, `src/Parsers/GenericCsvParser.php` | `mapToContactField()`, `setPayee(ContactData)`, `getPayee(): ?ContactData` | `tests/Entities/OfxEntityTest.php`, `tests/Parsers/GenericCsvParserTest.php` |
| TR-006 | External Dependency: ksfraser/contact-dto | `composer.json`, `src/Entities/Transaction.php`, `src/Entities/Payee.php` (deprecated stub) | `Ksfraser\Contact\DTO\ContactData` used throughout | `tests/Entities/OfxEntityTest.php` |
