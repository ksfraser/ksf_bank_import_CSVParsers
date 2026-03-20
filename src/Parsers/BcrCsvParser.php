<?php
/**
 * BCR Parser Plugin
 *
 * Specializes GenericCsvParser for Romanian BCR bank export format.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;
use Parsers\Entities\BankAccount;
use Parsers\Entities\Currency;

class BcrCsvParser extends GenericCsvParser
{
    /**
     * @inheritdoc
     */
    protected $labelAliases = [
        'date'            => ['data finalizarii tranzactiei', 'data inceput'],
        'payee'           => ['tranzactii finalizate (detalii)'],
        'debit'           => ['debit (suma)'],
        'credit'          => ['credit (suma)'],
        'accountNumber'   => ['contul pentru care s-a generat extrasul'],
        'currency'        => ['valuta'],
        'referenceNumber' => ['referinta oper. document'],
    ];

    /**
     * BCR uses separate columns for Debit and Credit sums.
     */
    protected function processRow(array $data, Statement $statement): void
    {
        $txData = [];
        $debit = 0.0;
        $credit = 0.0;
        $hasDebit = false;
        $hasCredit = false;

        foreach ($this->columnMap as $index => $property) {
            if (!isset($data[$index])) continue;
            
            $value = $data[$index];
            $property = $this->columnMap[$index];

            switch ($property) {
                case 'debit':
                    $debit = $this->formatAmount($value);
                    $hasDebit = true;
                    break;
                case 'credit':
                    $credit = $this->formatAmount($value);
                    $hasCredit = true;
                    break;
                case 'accountNumber':
                    if (empty($statement->accountNumber)) {
                        $statement->accountNumber = trim($value);
                        $this->populateBankAccount($statement, trim($value));
                    }
                    $txData['accountNumber'] = trim($value);
                    break;
                case 'currency':
                    if (empty($statement->currency)) {
                        $statement->currency = trim($value);
                        $statement->defaultCurrency = new Currency(trim($value));
                    }
                    $txData['commodity'] = trim($value);
                    break;
                case 'date':
                    $txData['date'] = $this->formatValue($value, 'date');
                    break;
                default:
                    $txData[$property] = $this->formatValue($value, $property);
            }
        }

        // Only process rows that have a date and either debit or credit
        if (empty($txData['date']) || (!$hasDebit && !$hasCredit)) {
            return;
        }

        // Create transaction
        if ($credit > 0) {
            $txData['amount'] = $credit;
            $txData['transactionDC'] = 'C';
        } elseif ($debit > 0) {
            $txData['amount'] = -$debit; 
            $txData['transactionDC'] = 'D';
        } else {
            return; // Zero transaction
        }

        $transaction = new Transaction($txData);
        $statement->addTransaction($transaction);
    }

    /**
     * BCR amounts use comma as thousands separator in some exports, 
     * but the legacy script removed all of them.
     */
    protected function formatAmount($value): float
    {
        // BCR often uses "1,234.56" as string
        $clean = str_replace([',', '"'], '', $value);
        return (float)$clean;
    }
}
