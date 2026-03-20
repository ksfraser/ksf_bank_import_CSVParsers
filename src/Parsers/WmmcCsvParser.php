<?php
/**
 * WMMC Parser Plugin
 *
 * Specializes GenericCsvParser for Walmart Mastercard CSV format.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model
 * @covers-requirement REQ-001
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;
use Parsers\Entities\BankAccount;

class WmmcCsvParser extends GenericCsvParser
{
    /**
     * @inheritdoc
     */
    protected $labelAliases = [
        'date'            => ['date', 'transaction date', 'transdate'],
        'postedDate'      => ['posted date', 'posteddate'],
        'payee'           => ['merchant', 'merchant name', 'description'],
        'amount'          => ['amount', 'amount num.'],
        'category'        => ['category', 'merchant category'],
        'referenceNumber' => ['reference number', 'referencenumber', 'transaction id'],
        'accountNumber'   => ['transaction card number', 'cardnumber', 'full account name', 'account name'],
        'status'          => ['status'],
        'city'            => ['merchant city'],
        'state'           => ['merchant state/province'],
        'country'         => ['merchant country'],
        'postalCode'      => ['merchant postal code/zip'],
        'reward'          => ['rewards'],
        'name'            => ['name on card'],
        'commodity'       => ['commodity/currency'],
    ];

    /**
     * @inheritdoc
     */
    protected function formatValue($value, string $property)
    {
        if ($property === 'amount') {
            return $this->formatAmount($value);
        }
        return parent::formatValue($value, $property);
    }

    /**
     * WMMC specific amount parsing
     * 
     * @param string $value
     * @return float
     */
    protected function formatAmount($value): float
    {
        $isNegative = (strpos($value, '-') !== false || (strpos($value, '(') !== false && strpos($value, ')') !== false));
        
        $clean = preg_replace('/[^0-9.]/', '', $value);

        if (substr_count($clean, '.') > 1) {
            $parts = explode('.', $clean);
            $decimal = array_pop($parts);
            $clean = implode('', $parts) . '.' . $decimal;
        }

        $val = (float)$clean;
        return $isNegative ? -$val : $val;
    }

    /**
     * WMMC accounts are always credit cards.
     *
     * @inheritdoc
     */
    protected function populateBankAccount(Statement $statement, string $accountId): void
    {
        if ($statement->bankAccount === null) {
            $statement->bankAccount = new BankAccount([
                'accountId'   => $accountId,
                'accountType' => BankAccount::TYPE_CREDITLINE,
            ]);
        }
    }
}

