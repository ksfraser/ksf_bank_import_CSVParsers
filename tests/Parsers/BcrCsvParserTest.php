<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\BcrCsvParser;
use Parsers\Entities\Statement;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */
class BcrCsvParserTest extends TestCase
{
    public function testBcrParsing()
    {
        $parser = new BcrCsvParser();
        $filePath = dirname(__DIR__, 2) . '/CSVs/statement_ro_bcr_csv.csv';

        if (!file_exists($filePath)) {
            $this->markTestSkipped('Fixture not found');
        }

        $statement = $parser->parse($filePath);
        
        $this->assertInstanceOf(Statement::class, $statement);
        $this->assertNotEmpty($statement->getTransactions());
        
        $txn = $statement->getTransactions()[0];
        
        // BCR Specifics
        $this->assertEquals(-414.31, $txn->amount);
        $this->assertEquals('D', $txn->transactionDC);
        
        $txn2 = $statement->getTransactions()[1];
        $this->assertEquals(1631.96, $txn2->amount);
        $this->assertEquals('C', $txn2->transactionDC);
        
        // BCR-style date from DD.MM.YYYY
        $this->assertEquals('2014-08-28', $txn->date);
    }
}
