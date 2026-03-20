<?php
namespace Parsers\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Parsers\Entities\Transaction;
use Parsers\Entities\Statement;

/**
 * @requirement REQ-002: Support for multi-line (split) transactions
 * @requirement REQ-003: Entity mapping to core bank import structures
 */
class EntityTest extends TestCase
{
    public function testTransactionCreation()
    {
        $data = [
            'date' => '2023-01-01',
            'payee' => 'Merchant1',
            'amount' => -100.50,
            'category' => 'Food',
            'memo' => 'Lunch',
            'transactionId' => 'TXN123',
            'referenceNumber' => 'REF456',
            'transactionDC' => 'D',
            'commodity' => 'CAD'
        ];
        
        $tx = new Transaction($data);
        
        $this->assertEquals('2023-01-01', $tx->date);
        $this->assertEquals('Merchant1', $tx->payee);
        $this->assertEquals(-100.50, $tx->amount);
        $this->assertEquals('D', $tx->transactionDC);
        $this->assertEquals('CAD', $tx->commodity);
    }

    public function testTransactionSplits()
    {
        $tx = new Transaction(['date' => '2023-01-01', 'payee' => 'Big Store', 'amount' => -100.00]);
        
        $split1 = new Transaction(['category' => 'Groceries', 'amount' => -60.00]);
        $split2 = new Transaction(['category' => 'Electronics', 'amount' => -40.00]);
        
        $tx->addSplit($split1);
        $tx->addSplit($split2);
        
        $this->assertTrue($tx->isSplit());
        $this->assertCount(2, $tx->getSplits());
        $this->assertEquals(-60.00, $tx->getSplits()[0]->amount);
    }

    public function testStatementAggregation()
    {
        $data = [
            'bankName' => 'My Bank',
            'accountNumber' => '123456789',
            'currency' => 'CAD',
            'startDate' => '2023-01-01',
            'endDate' => '2023-01-31',
            'openingBalance' => 1000.00,
            'closingBalance' => 1200.00,
        ];
        
        $statement = new Statement($data);
        
        $this->assertEquals('My Bank', $statement->bankName);
        $this->assertEquals('123456789', $statement->accountNumber);
        $this->assertEquals('CAD', $statement->currency);
        $this->assertEquals('2023-01-01', $statement->startDate);
        $this->assertEquals('2023-01-31', $statement->endDate);
        $this->assertEquals(1000.00, $statement->openingBalance);
        $this->assertEquals(1200.00, $statement->closingBalance);

        $statement->addTransaction(new Transaction(['amount' => 1500.00])); // Credit
        $statement->addTransaction(new Transaction(['amount' => -200.00])); // Debit
        
        $totals = $statement->getTotals();
        
        $this->assertEquals(1500.00, $totals['credits']);
        $this->assertEquals(200.00, $totals['debits']);
        $this->assertCount(2, $statement->getTransactions());
    }
}
