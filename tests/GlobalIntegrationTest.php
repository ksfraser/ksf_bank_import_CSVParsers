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
use Parsers\Entities\BankAccount;
use Parsers\Entities\Payee;

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

    /**
     * Verify WMMC parser populates BankAccount and payeeData on transactions.
     *
     * @requirement REQ-004: OFX-aligned entity model
     */
    public function testWmmcPayeeDataAndBankAccount()
    {
        $filePath = $this->csvDir . '/Transaction History_Current Transactions 20260311.csv';

        if (!file_exists($filePath) || filesize($filePath) === 0) {
            $this->markTestSkipped("WMMC fixture not found");
        }

        $parser = new WmmcCsvParser();
        $statement = $parser->parse($filePath);

        // BankAccount should be CREDITLINE
        $this->assertNotNull($statement->bankAccount, 'BankAccount should be populated');
        $this->assertEquals(BankAccount::TYPE_CREDITLINE, $statement->bankAccount->accountType);
        $this->assertNotEmpty($statement->bankAccount->accountId);

        // Find a transaction with merchant address data (WAL-MART with city/state)
        $found = false;
        foreach ($statement->getTransactions() as $tx) {
            if ($tx->payeeData !== null) {
                $payee = $tx->getPayee();
                $this->assertInstanceOf(Payee::class, $payee);
                $this->assertNotNull($payee->name);

                if ($payee->hasAddress()) {
                    $found = true;
                    $this->assertNotEmpty($payee->city);
                    $this->assertNotEmpty($payee->state);
                    break;
                }
            }
        }
        $this->assertTrue($found, 'At least one WMMC transaction should have structured address in payeeData');
    }

    /**
     * Verify BCR parser populates BankAccount and defaultCurrency.
     *
     * @requirement REQ-004: OFX-aligned entity model
     */
    public function testBcrBankAccountAndCurrency()
    {
        $filePath = $this->csvDir . '/statement_ro_bcr_csv.csv';

        if (!file_exists($filePath) || filesize($filePath) === 0) {
            $this->markTestSkipped("BCR fixture not found");
        }

        $parser = new BcrCsvParser();
        $statement = $parser->parse($filePath);

        $this->assertNotNull($statement->bankAccount, 'BCR BankAccount should be populated');
        $this->assertNotEmpty($statement->bankAccount->accountId);
        $this->assertNotNull($statement->defaultCurrency, 'BCR defaultCurrency should be populated');
        $this->assertNotEmpty((string)$statement->defaultCurrency);
    }

    /**
     * Verify RBC parser populates BankAccount as SAVINGS.
     *
     * @requirement REQ-004: OFX-aligned entity model
     */
    public function testRbcBankAccount()
    {
        $filePath = $this->csvDir . '/20260311 RBC HISA download-transactions.csv';

        if (!file_exists($filePath) || filesize($filePath) === 0) {
            $this->markTestSkipped("RBC fixture not found");
        }

        $parser = new RbcCsvParser();
        $statement = $parser->parse($filePath);

        $this->assertNotNull($statement->bankAccount, 'RBC BankAccount should be populated');
        $this->assertEquals(BankAccount::TYPE_SAVINGS, $statement->bankAccount->accountType);
    }
}
