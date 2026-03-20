<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\RbcCsvParser;
use Parsers\Entities\Statement;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
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
}
