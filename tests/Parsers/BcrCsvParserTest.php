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

    /**
     * Covers the "Zero transaction" return path: both debit and credit are 0.
     */
    public function testZeroTransactionIsSkipped()
    {
        $filePath = sys_get_temp_dir() . '/bcr_zero.csv';
        // BCR format: date, payee/details, debit, credit, account, currency, reference
        $header = "Data finalizarii tranzactiei,Tranzactii finalizate (detalii),Debit (suma),Credit (suma),Contul pentru care s-a generat extrasul,Valuta,Referinta oper. document\n";
        $zeroRow = "28.08.2014,Zero Txn,0,0,RO1234,RON,REF001\n";
        $validRow = "29.08.2014,Valid Txn,100.00,0,RO1234,RON,REF002\n";
        file_put_contents($filePath, $header . $zeroRow . $validRow);

        $parser = new BcrCsvParser();
        $statement = $parser->parse($filePath);

        // Only the valid row should produce a transaction, zero row is skipped
        $this->assertCount(1, $statement->getTransactions());
        $this->assertEquals(-100.00, $statement->getTransactions()[0]->amount);
        unlink($filePath);
    }

    /**
     * Covers the early return when row has no date and no debit/credit flags.
     */
    public function testRowWithoutDateIsSkipped()
    {
        $filePath = sys_get_temp_dir() . '/bcr_nodate.csv';
        $header = "Data finalizarii tranzactiei,Tranzactii finalizate (detalii),Debit (suma),Credit (suma),Contul pentru care s-a generat extrasul,Valuta,Referinta oper. document\n";
        $noDateRow = ",Missing Date Row,50.00,0,RO1234,RON,REF001\n";
        $validRow = "29.08.2014,Valid Txn,100.00,0,RO1234,RON,REF002\n";
        file_put_contents($filePath, $header . $noDateRow . $validRow);

        $parser = new BcrCsvParser();
        $statement = $parser->parse($filePath);

        $this->assertCount(1, $statement->getTransactions());
        $this->assertEquals('2014-08-29', $statement->getTransactions()[0]->date);
        unlink($filePath);
    }
}
