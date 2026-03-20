<?php
/**
 * Global Integration Test
 * 
 * Parses all available CSV files in the CSVs directory using their respective parsers,
 * counts transactions, and ensures all transaction amounts are non-zero.
 * 
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Tests;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\WmmcCsvParser;
use Parsers\Parsers\RbcCsvParser;
use Parsers\Parsers\BcrCsvParser;
use Parsers\Parsers\IngCsvParser;

class GlobalIntegrationTest extends TestCase
{
    private $csvDir;

    protected function setUp(): void
    {
        $this->csvDir = dirname(__DIR__) . '/CSVs';
    }

    /**
     * Data provider for CSV files and their corresponding parser classes.
     */
    public function csvFileProvider()
    {
        return [
            ['wmmc.csv', WmmcCsvParser::class],
            ['Transaction History_Current Transactions 20260311.csv', WmmcCsvParser::class],
            ['20260311 RBC HISA download-transactions.csv', RbcCsvParser::class],
            ['statement_ro_bcr_csv.csv', BcrCsvParser::class],
            ['20260112_1518404_transactions.csv', IngCsvParser::class],
        ];
    }

    /**
     * @dataProvider csvFileProvider
     */
    public function testParseFileAndValidateAmounts($fileName, $parserClass)
    {
        $filePath = $this->csvDir . '/' . $fileName;
        
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            $this->markTestSkipped("File not found or empty: $fileName");
        }

        $parser = new $parserClass();
        $statement = $parser->parse($filePath);
        $transactions = $statement->getTransactions();

        $this->assertNotEmpty($transactions, "No transactions found in $fileName");
        
        echo "\nFile: $fileName (" . count($transactions) . " transactions)\n";

        foreach ($transactions as $index => $txn) {
            $this->assertNotEquals(0.0, $txn->amount, "Zero amount found in $fileName at transaction index $index");
            $this->assertNotEmpty($txn->date, "Empty date found in $fileName at transaction index $index");
            
            // If it has splits, check those too
            if ($txn->isSplit()) {
                foreach ($txn->getSplits() as $splitIndex => $split) {
                    $this->assertNotEquals(0.0, $split->amount, "Zero amount found in split of $fileName at index $index:$splitIndex");
                }
            }
        }
    }
}
