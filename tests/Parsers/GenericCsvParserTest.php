<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\GenericCsvParser;
use Parsers\Entities\Statement;

/**
 * Concrete parser for testing purposes (Unit testable version of abstract base)
 */
class TestableParser extends GenericCsvParser
{
    // Uses base setColumnMap
}

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-002: Support for multi-line (split) transactions
 */
class GenericCsvParserTest extends TestCase
{
    private $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__) . '/fixtures';
    }

    public function testAutoDetectionWithHeaders()
    {
        $parser = new TestableParser();
        $filePath = $this->fixturesDir . '/test.csv';
        
        $statement = $parser->parse($filePath);
        
        $this->assertInstanceOf(Statement::class, $statement);
        $this->assertGreaterThan(0, count($statement->getTransactions()));
        
        // Check if important fields mapped
        $txn = $statement->getTransactions()[0];
        $this->assertNotEmpty($txn->date);
        $this->assertNotEmpty($txn->payee);
        $this->assertNotEmpty($txn->amount);
    }

    public function testMultiLineSplitGroupingByTransactionID()
    {
        $parser = new TestableParser();
        // Specifically look for GnuCash/CIBC split logic using ID
        $filePath = $this->fixturesDir . '/cibc_all.csv';
        
        $statement = $parser->parse($filePath);
        
        // In GnuCash export, each pair of rows shares a 'Transaction ID'
        // Row 1: Source (Asset), Row 2: Destination (Expense/Equity)
        // We expect these to be merged into 1 transaction with splits
        
        $txns = $statement->getTransactions();
        
        // Just checking the first few
        foreach ($txns as $tx) {
            if ($tx->transactionId === 'XXXXXXXXXXXXXXXXXXXXXXXXXXXX333c') {
                $this->assertTrue($tx->isSplit(), "Transaction with 333c ID should have segments");
                return;
            }
        }
        $this->fail("Did not find the expected grouped CIBC transaction");
    }

    public function testTransactionDCCalculation()
    {
        $parser = new TestableParser();
        $filePath = $this->fixturesDir . '/wmmc.csv';
        $statement = $parser->parse($filePath);
        
        foreach ($statement->getTransactions() as $tx) {
            if ($tx->amount < 0) {
                $this->assertEquals('D', $tx->transactionDC);
            } else {
                $this->assertEquals('C', $tx->transactionDC);
            }
        }
    }

    public function testAmountFormatting()
    {
        $parser = new TestableParser();
        
        // Reflection to test protected formatValue
        $method = new \ReflectionMethod(TestableParser::class, 'formatValue');
        $method->setAccessible(true);
        
        $this->assertEquals(-123.45, $method->invoke($parser, '(123.45)', 'amount'));
        $this->assertEquals(1234.56, $method->invoke($parser, '$1,234.56', 'amount'));
        $this->assertEquals('2023-12-25', $method->invoke($parser, 'Dec 25, 2023', 'date'));
        $this->assertEquals('Raw Date', $method->invoke($parser, 'Raw Date', 'date'));
        $this->assertEquals('Clean Value', $method->invoke($parser, '  "Clean Value"  ', 'description'));
        $this->assertNull($method->invoke($parser, '', 'any'));
        $this->assertNull($method->invoke($parser, null, 'any'));
    }

    public function testMissingAndEmptyFile()
    {
        $parser = new TestableParser();
        $statement = $parser->parse('/non/existent/file.csv');
        $this->assertEmpty($statement->getTransactions());

        $filePath = sys_get_temp_dir() . '/empty_test.csv';
        file_put_contents($filePath, '');
        $statement = $parser->parse($filePath);
        $this->assertEmpty($statement->getTransactions());
        unlink($filePath);
    }

    public function testNoHeaderDataRow()
    {
        $filePath = sys_get_temp_dir() . '/noheader.csv';
        file_put_contents($filePath, "2023-01-01,Payee,100.00\n");

        $parser = new TestableParser();
        $parser->setColumnMap([0 => 'date', 1 => 'payee', 2 => 'amount']);
        
        $statement = $parser->parse($filePath);

        $this->assertCount(1, $statement->getTransactions());
        $this->assertEquals('2023-01-01', $statement->getTransactions()[0]->date);
        unlink($filePath);
    }
}
