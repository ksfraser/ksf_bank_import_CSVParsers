<?php
/**
 * GenericCsvParser Base Class
 *
 * Implements the core logic for CSV parsing with auto-recognition.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-002: Support for multi-line (split) transactions
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;
use Parsers\Entities\BankAccount;
use Parsers\Entities\Payee;

abstract class GenericCsvParser
{
    /** @var string CSV delimiter */
    protected $delimiter = ',';

    /** @var string CSV enclosure */
    protected $enclosure = '"';

    /** @var string CSV escape character */
    protected $escape = '\\';

    /** @var bool Whether the CSV has a header row */
    protected $hasHeader = true; // Default to true so first row is checked as header

    /** @var array Mapping of column index to Transaction entity property */
    protected $columnMap = [];

    /** @var array Map of header labels to their expected entity property */
    protected $labelAliases = [
        'date'            => ['date', 'transaction date', 'posted dated', 'effective date', 't-date', 'trans date'],
        'payee'           => ['payee', 'merchant', 'merchant name', 'description', 'notes', 'memo'],
        'amount'          => ['amount', 'transaction amount', 'net amount', 'amount num.', 'value num.'],
        'category'        => ['category', 'type', 'transaction type', 'merchant category'],
        'referenceNumber' => ['reference', 'reference number', 'confirmation', 'number', 'cheque number', 'check number'],
        'transactionId'   => ['transaction id', 'txid', 'trans id'],
        'commodity'       => ['commodity/currency', 'currency'],
    ];

    /** @var Transaction|null The current transaction being assembled (for multi-line/splits) */
    protected $currentTransaction = null;

    /** @var bool Whether to force use of columnMap and skip auto-detection */
    protected $skipDetection = false;

    /**
     * Set a fixed column mapping and skip header detection.
     * 
     * @param array $map
     */
    public function setColumnMap(array $map): void
    {
        $this->columnMap = $map;
        $this->skipDetection = true;
    }

    /**
     * Parse a CSV file into a Statement entity.
     * 
     * @param string $filePath
     * @return Statement
     */
    public function parse(string $filePath): Statement
    {
        $statement = new Statement();
        $this->currentTransaction = null;

        if (!file_exists($filePath)) {
            return $statement;
        }

        if (($handle = fopen($filePath, "r")) !== FALSE) {
            $firstRow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape);
            
            if ($firstRow === FALSE) {
                fclose($handle);
                return $statement;
            }

            // Attempt auto-recognition unless skipped
            if (!$this->skipDetection) {
                $this->detectColumns($firstRow);
            } else {
                $this->hasHeader = false;
            }

            // If we detected headers, we've already consumed the first row.
            // If the first row was actually data, we need to process it.
            if ($this->hasHeader === false) {
                $this->processRow($firstRow, $statement);
            }

            while (($data = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== FALSE) {
                $this->processRow($data, $statement);
            }

            // Complete any pending transaction at the end of the file
            $this->finalizeCurrentTransaction($statement);

            fclose($handle);
        }

        return $statement;
    }

    /**
     * Detect column mapping from the first row.
     * 
     * @param array $row
     */
    protected function detectColumns(array $row): void
    {
        $hasMatch = false;
        foreach ($row as $index => $label) {
            // Remove BOM if present in the first column
            if ($index === 0) {
                $label = preg_replace('/^\xEF\xBB\xBF/', '', $label);
            }
            
            $normalizedLabel = trim(strtolower($label), "\"' \t\n\r\0\x0B$");
            foreach ($this->labelAliases as $property => $aliases) {
                if (in_array($normalizedLabel, $aliases)) {
                    $this->columnMap[$index] = $property;
                    $hasMatch = true;
                    break;
                }
            }
        }

        $this->hasHeader = $hasMatch;
    }

    /**
     * Map a single CSV row to a Transaction entity and add it to the Statement.
     * 
     * Handles state for multi-line (split) transactions and ID grouping.
     * 
     * @param array $data
     * @param Statement $statement
     */
    protected function processRow(array $data, Statement $statement): void
    {
        error_log("PROCESS ROW: " . implode('|', $data));
        $txData = [];
        $addressParts = [];
        $payeeFields = [];
        foreach ($this->columnMap as $index => $property) {
            if (isset($data[$index])) {
                $value = $this->formatValue($data[$index], $property);
                
                // Collect address components for Payee entity and consolidated memo
                if (in_array($property, ['city', 'state', 'country', 'postalCode'])) {
                    if (!empty($value)) {
                        $addressParts[] = $value;
                        $payeeFields[$property] = $value;
                    }
                    continue;
                }

                $txData[$property] = $value;
                
                // Track account number from header-detected column if not already set on statement
                if ($property === 'accountNumber' && empty($statement->accountNumber) && !empty($value)) {
                    $statement->accountNumber = $value;
                    $this->populateBankAccount($statement, $value);
                }
            }
        }

        // Consolidate address into memo if address exists
        if (!empty($addressParts)) {
            $addressStr = implode(', ', $addressParts);
            $existingMemo = isset($txData['memo']) ? trim($txData['memo']) : '';
            $txData['memo'] = !empty($existingMemo) ? $existingMemo . ' | ' . $addressStr : $addressStr;
        }

        // Build structured Payee and serialize to payeeData JSON
        $payeeName = isset($txData['payee']) ? $txData['payee'] : null;
        $payeeCategory = isset($txData['category']) ? $txData['category'] : null;
        if ($payeeName !== null || !empty($payeeFields)) {
            $payeeFields['name'] = $payeeName;
            if ($payeeCategory !== null) {
                $payeeFields['category'] = $payeeCategory;
            }
            $payee = new Payee($payeeFields);
            $txData['payeeData'] = $payee->toJson();
        }

        if (empty($txData)) {
            return;
        }

        // Logic for grouping splits (GnuCash scenario)
        if (!empty($txData['transactionId']) && $this->currentTransaction && $this->currentTransaction->transactionId === $txData['transactionId']) {
            $split = new Transaction($txData);
            $this->currentTransaction->addSplit($split);
            return;
        }

        // Standard state-machine grouping (Sequence based)
        if (!empty($txData['date'])) {
            $this->finalizeCurrentTransaction($statement);
            $this->currentTransaction = new Transaction($txData);
            // Set TransactionDC
            if (isset($txData['amount'])) {
                $this->currentTransaction->transactionDC = ($txData['amount'] >= 0) ? 'C' : 'D';
            }
        } elseif (!empty($txData['payee']) || isset($txData['amount'])) {
            if ($this->currentTransaction) {
                $split = new Transaction($txData);
                $this->currentTransaction->addSplit($split);
            }
        }
    }

    /**
     * Push the current transaction to the statement.
     * 
     * @param Statement $statement
     */
    protected function finalizeCurrentTransaction(Statement $statement): void
    {
        if ($this->currentTransaction) {
            $statement->addTransaction($this->currentTransaction);
            $this->currentTransaction = null;
        }
    }

    /**
     * Format a value according to its expected type.
     * 
     * @param string|null $value
     * @param string $property
     * @return mixed
     */
    protected function formatValue($value, string $property)
    {
        if ($value === null) return null;
        $trimmed = trim($value, "\"' \t\n\r\0\x0B");
        if ($trimmed === '') return null;

        switch ($property) {
            case 'amount':
                // Check for negative signs or parentheses
                $isNegative = (strpos($trimmed, '-') !== false || (strpos($trimmed, '(') !== false && strpos($trimmed, ')') !== false));
                
                // Allow digits and the first decimal point found
                $clean = preg_replace('/[^0-9.]/', '', $trimmed);

                // Handle the case where the amount has extra dots
                if (substr_count($clean, '.') > 1) {
                    $parts = explode('.', $clean);
                    $decimal = array_pop($parts);
                    $clean = implode('', $parts) . '.' . $decimal;
                }

                if ($clean === '' || $clean === '.') {
                    $final = 0.0;
                } else {
                    $val = (float) $clean;
                    $final = $isNegative ? -$val : $val;
                }
                
                return $final;
            case 'date':
                // Try common date formats
                $ts = @strtotime($trimmed);
                return $ts ? date('Y-m-d', $ts) : $trimmed;
            default:
                return $trimmed;
        }
    }

    /**
     * Populate the Statement's BankAccount entity from account number.
     *
     * @param Statement $statement
     * @param string $accountId
     */
    protected function populateBankAccount(Statement $statement, string $accountId): void
    {
        if ($statement->bankAccount === null) {
            $statement->bankAccount = new BankAccount([
                'accountId' => $accountId,
            ]);
        }
    }
}
