<?php
/**
 * ING Parser Plugin
 *
 * Specializes GenericCsvParser for Romanian ING bank export format.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Parsers;

use Parsers\Entities\Statement;
use Parsers\Entities\Transaction;

class IngCsvParser extends GenericCsvParser
{
    /**
     * @inheritdoc
     */
    protected $columnMap = [
        0 => 'accountNumber',
        1 => 'date',
        2 => 'amount',
        3 => 'payee',
    ];

    /**
     * @inheritdoc
     */
    protected $skipDetection = true;

    /**
     * @inheritdoc
     */
    protected $labelAliases = [];

    /**
     * ING files sometimes lack a header.
     * Based on the sample, format is: Account, Date, Amount, Description
     */
    public function parse(string $filePath): Statement
    {
        $this->setColumnMap([
            0 => 'accountNumber',
            1 => 'date',
            2 => 'amount',
            3 => 'payee'
        ]);
        return parent::parse($filePath);
    }
}
