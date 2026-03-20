<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\RbcCsvParser;
use Parsers\Entities\Statement;
use Parsers\Entities\BankAccount;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model
 */
class RbcCsvParserTest extends TestCase
{
    public function testRbcParsing()
    {
        $parser = new RbcCsvParser();
        $filePath = dirname(__DIR__, 2) . '/CSVs/20260311 RBC HISA download-transactions.csv';

        if (!file_exists($filePath)) {
            $this->markTestSkipped('Fixture not found');
        }

        $statement = $parser->parse($filePath);
        
        $this->assertInstanceOf(Statement::class, $statement);
        $this->assertNotEmpty($statement->getTransactions());
        
        $txn = $statement->getTransactions()[0];
        
        // RBC formatting check
        $this->assertEquals('2026-03-02', $txn->date);
        $this->assertEquals(0.08, $txn->amount);
        $this->assertEquals('DEPOSIT INTEREST', $txn->payee);
    }

    /**
     * Covers populateBankAccount with SAVINGS type and formatAmount edge cases.
     */
    public function testRbcBankAccountAndAmountEdgeCases()
    {
        $filePath = sys_get_temp_dir() . '/rbc_test.csv';
        $header = "Account Type,Account Number,Transaction Date,Cheque Number,Description 1,Description 2,CAD$,USD$\n";
        $row = "Savings,12345,2024-01-15,,TestPayee,memo detail,50.00,\n";
        file_put_contents($filePath, $header . $row);

        $parser = new RbcCsvParser();
        $statement = $parser->parse($filePath);

        $this->assertNotNull($statement->bankAccount);
        $this->assertEquals(BankAccount::TYPE_SAVINGS, $statement->bankAccount->accountType);
        $this->assertEquals('12345', $statement->bankAccount->accountId);
        $this->assertCount(1, $statement->getTransactions());
        $this->assertEquals(50.00, $statement->getTransactions()[0]->amount);
        unlink($filePath);
    }

    /**
     * Covers the RBC formatAmount null/empty return path.
     */
    public function testRbcFormatAmountEmptyReturnsNull()
    {
        $parser = new RbcCsvParser();
        $method = new \ReflectionMethod(RbcCsvParser::class, 'formatAmount');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($parser, ''));
        $this->assertNull($method->invoke($parser, '   '));
        $this->assertNull($method->invoke($parser, null));
    }

    /**
     * Covers early return when row has no date or null amount.
     */
    public function testRowWithoutDateOrAmountIsSkipped()
    {
        $filePath = sys_get_temp_dir() . '/rbc_nodate.csv';
        $header = "Account Type,Account Number,Transaction Date,Cheque Number,Description 1,Description 2,CAD\$,USD\$\n";
        $noDateRow = "Savings,12345,,,NoDatePayee,memo,,\n";
        $validRow = "Savings,12345,2024-01-15,,ValidPayee,memo2,75.00,\n";
        file_put_contents($filePath, $header . $noDateRow . $validRow);

        $parser = new RbcCsvParser();
        $statement = $parser->parse($filePath);

        $this->assertCount(1, $statement->getTransactions());
        $this->assertEquals('ValidPayee', $statement->getTransactions()[0]->payee);
        unlink($filePath);
    }
}
