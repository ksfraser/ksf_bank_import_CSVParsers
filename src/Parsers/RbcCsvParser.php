<?php
/**
 * RBC Parser Plugin
 *
 * Specializes GenericCsvParser for RBC bank export format.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;

class RbcCsvParser extends GenericCsvParser
{
    /** @var string Overridden for RBC defaults */
    protected $delimiter = ',';
    protected $labelAliases = [
        'date'            => ['transaction date', 'transactiondate', 'date'],
        'payee'           => ['description 1', 'description1', 'payee'],
        'memo'            => ['description 2', 'description2', 'memo'],
        'amount_cad'      => ['cad$', 'cad'],
        'amount_usd'      => ['usd$', 'usd'],
        'amount'          => ['amount'],
        'accountNumber'   => ['account number', 'accountnumber'],
        'referenceNumber' => ['cheque number', 'chequenumber'],
        'category'        => ['account type', 'accounttype'],
    ];

    /**
     * RBC has separate columns for CAD$ and USD$.
     */
    protected function processRow(array $data, Statement $statement): void
    {
        $txData = [];
        $finalAmount = null;

        foreach ($this->columnMap as $index => $property) {
            if (!isset($data[$index])) continue;
            
            $value = $data[$index];
            
            if ($property === 'amount_cad' || $property === 'amount_usd' || $property === 'amount') {
                $amt = $this->formatAmount($value);
                if ($amt !== null && $amt !== 0.0) {
                    $finalAmount = $amt;
                }
                continue;
            }

            $txData[$property] = $this->formatValue($value, $property);

            if ($property === 'accountNumber' && empty($statement->accountNumber) && !empty($txData[$property])) {
                $statement->accountNumber = $txData[$property];
            }
        }

        if (empty($txData['date']) || $finalAmount === null) {
            return;
        }

        $txData['amount'] = $finalAmount;
        $transaction = new Transaction($txData);
        $transaction->transactionDC = ($transaction->amount >= 0) ? 'C' : 'D';
        $statement->addTransaction($transaction);
    }

    /**
     * @param string $value
     * @return float|null
     */
    protected function formatAmount($value): ?float
    {
        if ($value === null || trim($value) === '') return null;
        $clean = preg_replace('/[^0-9\.\-]/', '', $value);
        return (float)$clean;
    }
}
