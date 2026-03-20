<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\GenericCsvParser;
use Parsers\Entities\Statement;
use Parsers\Entities\BankAccount;
use Ksfraser\Contact\DTO\ContactData;

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
 * @requirement REQ-004: OFX-aligned entity model
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
        
        $txn = $statement->getTransactions()[0];
        $this->assertNotEmpty($txn->date);
        $this->assertNotEmpty($txn->payee);
        $this->assertNotEmpty($txn->amount);
    }

    public function testMultiLineSplitGroupingByTransactionID()
    {
        $parser = new TestableParser();
        $filePath = $this->fixturesDir . '/cibc_all.csv';
        
        $statement = $parser->parse($filePath);
        
        $txns = $statement->getTransactions();
        
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

    /**
     * Covers the extra-dots branch in formatValue amount and the zero-amount branch.
     */
    public function testAmountFormattingEdgeCases()
    {
        $parser = new TestableParser();
        $method = new \ReflectionMethod(TestableParser::class, 'formatValue');
        $method->setAccessible(true);

        // Multiple dots: "1.234.56" should become 1234.56
        $this->assertEquals(1234.56, $method->invoke($parser, '1.234.56', 'amount'));

        // Only a dot: should return 0.0
        $this->assertEquals(0.0, $method->invoke($parser, '.', 'amount'));

        // Only non-numeric chars (no digits at all): should return 0.0
        $this->assertEquals(0.0, $method->invoke($parser, '$', 'amount'));

        // Negative with multiple dots: "-1.234.56" -> -1234.56
        $this->assertEquals(-1234.56, $method->invoke($parser, '-1.234.56', 'amount'));
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

    /**
     * Covers the populateBankAccount() base class method.
     */
    public function testPopulateBankAccountFromGenericParser()
    {
        $filePath = sys_get_temp_dir() . '/bankacct_test.csv';
        file_put_contents($filePath, "2023-01-01,Store,50.00,ACCT-999\n");

        $parser = new TestableParser();
        $parser->setColumnMap([0 => 'date', 1 => 'payee', 2 => 'amount', 3 => 'accountNumber']);
        $statement = $parser->parse($filePath);

        $this->assertNotNull($statement->bankAccount, 'BankAccount should be populated');
        $this->assertEquals('ACCT-999', $statement->bankAccount->accountId);
        $this->assertNull($statement->bankAccount->accountType);
        $this->assertEquals('ACCT-999', $statement->accountNumber);
        unlink($filePath);
    }

    /**
     * Covers processRow when data maps to no known columns — empty txData return.
     */
    public function testProcessRowWithUnmappedColumnsReturnsNoTransaction()
    {
        $filePath = sys_get_temp_dir() . '/unmapped_test.csv';
        file_put_contents($filePath, "Foo,Bar,Baz\nval1,val2,val3\n");

        $parser = new TestableParser();
        $statement = $parser->parse($filePath);

        // No headers matched, first row treated as data but nothing maps
        $this->assertEmpty($statement->getTransactions());
        unlink($filePath);
    }

    /**
     * Covers the sequence-based split path: row without date but with payee/amount
     * gets added as a split to the current transaction.
     */
    public function testSequenceBasedSplitGrouping()
    {
        $filePath = sys_get_temp_dir() . '/split_seq_test.csv';
        // First row = header; second row = parent (has date); third row = split (no date, has payee+amount)
        $content = "Date,Payee,Amount\n2023-01-01,MainStore,-100.00\n,SubItem1,-60.00\n,SubItem2,-40.00\n";
        file_put_contents($filePath, $content);

        $parser = new TestableParser();
        $statement = $parser->parse($filePath);

        $txns = $statement->getTransactions();
        $this->assertCount(1, $txns, 'Should have 1 parent transaction');
        $this->assertTrue($txns[0]->isSplit(), 'Parent should have splits');
        $this->assertCount(2, $txns[0]->getSplits());
        $this->assertEquals(-60.00, $txns[0]->getSplits()[0]->amount);
        $this->assertEquals(-40.00, $txns[0]->getSplits()[1]->amount);
        unlink($filePath);
    }

    /**
     * Covers payeeData JSON population in processRow.
     */
    public function testPayeeDataPopulatedOnTransaction()
    {
        $filePath = sys_get_temp_dir() . '/payeedata_test.csv';
        file_put_contents($filePath, "Date,Payee,Amount,Category\n2023-01-01,TestMerchant,-50.00,Groceries\n");

        $parser = new TestableParser();
        $statement = $parser->parse($filePath);

        $tx = $statement->getTransactions()[0];
        $this->assertNotNull($tx->payeeData, 'payeeData should be populated');
        $this->assertJson($tx->payeeData);

        $payee = $tx->getPayee();
        $this->assertInstanceOf(ContactData::class, $payee);
        $this->assertEquals('TestMerchant', $payee->name);
        $this->assertEquals('Groceries', $payee->tags);
        unlink($filePath);
    }

    /**
     * Covers the address-to-memo consolidation with existing memo.
     */
    public function testAddressConsolidationWithExistingMemo()
    {
        $filePath = sys_get_temp_dir() . '/addr_memo_test.csv';
        // Parser needs to recognize both memo and city columns
        // Use a custom column map for this test
        file_put_contents($filePath, "2023-01-01,Store,-50.00,Existing memo,Calgary,AB\n");

        $parser = new TestableParser();
        $parser->setColumnMap([
            0 => 'date',
            1 => 'payee',
            2 => 'amount',
            3 => 'memo',
            4 => 'city',
            5 => 'state',
        ]);
        $statement = $parser->parse($filePath);

        $tx = $statement->getTransactions()[0];
        $this->assertStringContainsString('Existing memo', $tx->memo);
        $this->assertStringContainsString('Calgary', $tx->memo);
        $this->assertStringContainsString('|', $tx->memo);
        unlink($filePath);
    }

    /**
     * Covers detectColumns with BOM in first column header.
     */
    public function testDetectColumnsWithBom()
    {
        $filePath = sys_get_temp_dir() . '/bom_test.csv';
        $bom = "\xEF\xBB\xBF";
        file_put_contents($filePath, $bom . "Date,Payee,Amount\n2023-06-15,BomStore,-25.00\n");

        $parser = new TestableParser();
        $statement = $parser->parse($filePath);

        $txns = $statement->getTransactions();
        $this->assertCount(1, $txns);
        $this->assertEquals('2023-06-15', $txns[0]->date);
        $this->assertEquals('BomStore', $txns[0]->payee);
        unlink($filePath);
    }

    /**
     * Covers mapToContactField for CSV-to-ContactData field name translation.
     */
    public function testMapToContactField()
    {
        $parser = new TestableParser();
        $method = new \ReflectionMethod(TestableParser::class, 'mapToContactField');
        $method->setAccessible(true);

        $this->assertEquals('state_province', $method->invoke($parser, 'state'));
        $this->assertEquals('postal_code', $method->invoke($parser, 'postalCode'));
        $this->assertEquals('address_line_1', $method->invoke($parser, 'address1'));
        // Unmapped fields pass through
        $this->assertEquals('city', $method->invoke($parser, 'city'));
        $this->assertEquals('country', $method->invoke($parser, 'country'));
    }
}
