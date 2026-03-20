<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\WmmcCsvParser;
use Parsers\Entities\Statement;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
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
}
