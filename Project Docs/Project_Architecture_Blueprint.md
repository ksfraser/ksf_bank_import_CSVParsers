# Project Architecture Blueprint: WMMC CSV Parser

## Overview
This project provides a PHP-based parser for Walmart MasterCard (WMMC) CSV credit card statements. It is designed to be integrated into a larger bank import system, following the standards defined in [AGENTS.md](AGENTS.md).

## Technology Stack
- **Language**: PHP 7.3+
- **Data Format**: CSV (input), JSON (metadata)
- **Architecture Pattern**: Layered Architecture (Plugin/Module style)

## Core Components

### 1. Metadata: [parser.json](parser.json)
Defines the parser's identity, namespace, and configuration.
- **Namespace**: `Parsers\ro_wmmc_csv`
- **Class**: `ro_wmmc_csv_parser`
- **Filetype**: `csv`

### 2. Implementation: [ro_wmmc_csv_parser.php](ro_wmmc_csv_parser.php)
Contains the logic for parsing the CSV content and converting it into standardized `statement` and `transaction` objects.
- **Base Class**: `parser` (expected to be provided by the core module)
- **Key Methods**:
    - `parse($content, $static_data, $debug)`: Main entry point for parsing CSV text.
    - `_combine_array(&$row, $key, $header)`: Helper for array alignment.

## Architectural Patterns

### Module Architecture
The project follows a plugin architecture where the `ro_wmmc_csv_parser` extends a base `parser` class. This allows the core system to load and execute different parsers dynamically based on the configuration in `parser.json`.

### Data Flow
1. **Input**: Raw CSV content and static data (bank name, account, currency).
2. **Parsing**: 
    - Header detection and normalization.
    - Iterative processing of CSV rows.
    - State management for multi-line transactions (if applicable).
3. **Output**: An array of `statement` objects, each containing `transaction` objects.

## Compliance with AGENTS.md
The current implementation needs alignment with the following standards:
- **SOLID Principles**: The `parse` method is currently doing too much (SRP violation).
- **Polymorphism over Conditionals**: Logic for different CSV versions should be refactored into strategies.
- **Type Hinting**: Missing strict typing for parameters and return values.
- **Documentation**: Needs updated PHPDoc blocks with requirement mappings.

## Extension Points
- **New CSV Formats**: Can be added by updating the header mapping logic or implementing a strategy pattern for format detection.
- **Additional Data Fields**: The `statement` and `transaction` objects can be extended with more metadata as required by the core system.
