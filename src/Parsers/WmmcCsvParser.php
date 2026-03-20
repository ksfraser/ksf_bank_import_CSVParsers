<?php
/**
 * WMMC Parser Plugin
 *
 * Specializes GenericCsvParser for Walmart Mastercard CSV format.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @covers-requirement REQ-001
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;

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
        // Strip out anything that is NOT a digit, a decimal point, or a negative sign
        // This handles cases like "-$300.00" or "$94.59"
        $isNegative = (strpos($value, '-') !== false || (strpos($value, '(') !== false && strpos($value, ')') !== false));
        
        $clean = preg_replace('/[^0-9.]/', '', $value);

        // Handle the case where the amount has extra dots (unlikely but safe)
        if (substr_count($clean, '.') > 1) {
            $parts = explode('.', $clean);
            $decimal = array_pop($parts);
            $clean = implode('', $parts) . '.' . $decimal;
        }

        $val = (float)$clean;
        return $isNegative ? -$val : $val;
        parent::processRow($data, $statement);
    }
}

