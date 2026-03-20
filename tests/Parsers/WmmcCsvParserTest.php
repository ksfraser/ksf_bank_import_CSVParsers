<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\WmmcCsvParser;
use Parsers\Entities\Statement;
use Parsers\Entities\BankAccount;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model
 */
class WmmcCsvParserTest extends TestCase
{
    public function testWmmcParsing()
    {
        $parser = new WmmcCsvParser();
        $filePath = dirname(__DIR__) . '/fixtures/wmmc.csv';
        
        $statement = $parser->parse($filePath);
        
        $this->assertInstanceOf(Statement::class, $statement);
        $this->assertNotEmpty($statement->getTransactions());
        
        $txn = $statement->getTransactions()[0];
        
        // WMMC specific formatting check
        $this->assertIsFloat($txn->amount);
        $this->assertEquals('D', $txn->transactionDC);
        
        // Ensure account number was captured from rows if present
        $this->assertNotEmpty($statement->accountNumber);
    }

    /**
     * Covers the multi-dot branch in formatAmount (European format: "1.234.56").
     */
    public function testFormatAmountWithMultipleDots()
    {
        $parser = new WmmcCsvParser();
        $method = new \ReflectionMethod(WmmcCsvParser::class, 'formatAmount');
        $method->setAccessible(true);

        // European-style: "1.234.56" → 1234.56
        $this->assertEquals(1234.56, $method->invoke($parser, '1.234.56'));
        // With negative
        $this->assertEquals(-1234.56, $method->invoke($parser, '-1.234.56'));
        // Normal single decimal
        $this->assertEquals(100.50, $method->invoke($parser, '100.50'));
        // Parenthetical negative
        $this->assertEquals(-50.00, $method->invoke($parser, '($50.00)'));
    }

    /**
     * Covers formatValue non-amount path (delegates to parent).
     */
    public function testFormatValueNonAmountDelegatesToParent()
    {
        $parser = new WmmcCsvParser();
        $method = new \ReflectionMethod(WmmcCsvParser::class, 'formatValue');
        $method->setAccessible(true);

        $this->assertEquals('TestPayee', $method->invoke($parser, ' TestPayee ', 'payee'));
        $this->assertNull($method->invoke($parser, '', 'payee'));
    }

    /**
     * Covers populateBankAccount sets CREDITLINE type.
     */
    public function testPopulateBankAccountCreditLine()
    {
        $parser = new WmmcCsvParser();
        $filePath = dirname(__DIR__) . '/fixtures/wmmc.csv';
        $statement = $parser->parse($filePath);

        $this->assertNotNull($statement->bankAccount);
        $this->assertEquals(BankAccount::TYPE_CREDITLINE, $statement->bankAccount->accountType);
    }
}
