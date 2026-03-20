<?php
namespace Parsers\Tests\Parsers;

use PHPUnit\Framework\TestCase;
use Parsers\Parsers\IngCsvParser;
use Parsers\Entities\Statement;

/**
 * @requirement REQ-001: Semi-auto column recognition logic
 * @requirement REQ-003: Entity mapping to core bank import structures
 */
class IngCsvParserTest extends TestCase
{
    public function testIngParsing()
    {
        $parser = new IngCsvParser();
        $filePath = dirname(__DIR__, 2) . '/CSVs/20260112_1518404_transactions.csv';

        if (!file_exists($filePath)) {
            $this->markTestSkipped('Fixture not found');
        }

        $statement = $parser->parse($filePath);
        
        $this->assertInstanceOf(Statement::class, $statement);
        $this->assertNotEmpty($statement->getTransactions());
        
        $txn = $statement->getTransactions()[0];
        
        // ING Specifics (no header detection expected here, using manual mapping if needed)
        // Since GenericCsvParser has decent defaults, let's see what it picks up.
    }
}
