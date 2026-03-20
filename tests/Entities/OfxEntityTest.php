<?php
/**
 * Tests for OFX-aligned entities: BankAccount, Payee, Currency, Balance.
 * Also tests Transaction::payeeData JSON round-trip and Statement new properties.
 *
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model
 */

namespace Parsers\Tests\Entities;

use PHPUnit\Framework\TestCase;
use Parsers\Entities\BankAccount;
use Parsers\Entities\Payee;
use Parsers\Entities\Currency;
use Parsers\Entities\Balance;
use Parsers\Entities\Transaction;
use Parsers\Entities\Statement;

class OfxEntityTest extends TestCase
{
    // ──────────────────────────────────────────────
    // BankAccount
    // ──────────────────────────────────────────────

    public function testBankAccountCreation()
    {
        $acct = new BankAccount([
            'bankId'        => '004',
            'accountId'     => '************2251',
            'accountType'   => BankAccount::TYPE_CREDITLINE,
            'branchId'      => '00001',
            'accountHolder' => 'KEVIN FRASER',
        ]);

        $this->assertEquals('004', $acct->bankId);
        $this->assertEquals('************2251', $acct->accountId);
        $this->assertEquals(BankAccount::TYPE_CREDITLINE, $acct->accountType);
        $this->assertEquals('00001', $acct->branchId);
        $this->assertEquals('KEVIN FRASER', $acct->accountHolder);
    }

    public function testBankAccountConstants()
    {
        $this->assertEquals('CHECKING', BankAccount::TYPE_CHECKING);
        $this->assertEquals('SAVINGS', BankAccount::TYPE_SAVINGS);
        $this->assertEquals('CREDITLINE', BankAccount::TYPE_CREDITLINE);
        $this->assertEquals('MONEYMRKT', BankAccount::TYPE_MONEYMRKT);
    }

    public function testBankAccountDisplayName()
    {
        $full = new BankAccount([
            'bankId'      => '004',
            'accountId'   => '123456',
            'accountType' => BankAccount::TYPE_SAVINGS,
        ]);
        $this->assertEquals('004 123456 (SAVINGS)', $full->getDisplayName());

        $minimal = new BankAccount(['accountId' => '789']);
        $this->assertEquals('789', $minimal->getDisplayName());

        $empty = new BankAccount();
        $this->assertEquals('(unknown account)', $empty->getDisplayName());
    }

    public function testBankAccountToArray()
    {
        $acct = new BankAccount([
            'bankId'    => '004',
            'accountId' => '123456',
        ]);
        $arr = $acct->toArray();

        $this->assertArrayHasKey('bankId', $arr);
        $this->assertArrayHasKey('accountId', $arr);
        $this->assertArrayHasKey('accountType', $arr);
        $this->assertArrayHasKey('branchId', $arr);
        $this->assertArrayHasKey('accountHolder', $arr);
        $this->assertEquals('004', $arr['bankId']);
        $this->assertNull($arr['accountType']);
    }

    // ──────────────────────────────────────────────
    // Payee
    // ──────────────────────────────────────────────

    public function testPayeeCreation()
    {
        $payee = new Payee([
            'name'       => 'WAL-MART #1050',
            'city'       => 'AIRDRIE',
            'state'      => 'AB',
            'country'    => 'CAN',
            'postalCode' => 'T4B 3G5',
            'category'   => 'Grocery Stores and Supermarkets',
        ]);

        $this->assertEquals('WAL-MART #1050', $payee->name);
        $this->assertEquals('AIRDRIE', $payee->city);
        $this->assertEquals('AB', $payee->state);
        $this->assertEquals('CAN', $payee->country);
        $this->assertEquals('T4B 3G5', $payee->postalCode);
        $this->assertEquals('Grocery Stores and Supermarkets', $payee->category);
        $this->assertNull($payee->phone);
        $this->assertNull($payee->address1);
    }

    public function testPayeeAddressString()
    {
        $full = new Payee([
            'city'       => 'AIRDRIE',
            'state'      => 'AB',
            'country'    => 'CAN',
            'postalCode' => 'T4B 3G5',
        ]);
        $this->assertEquals('AIRDRIE, AB, CAN, T4B 3G5', $full->getAddressString());

        $partial = new Payee(['city' => 'CALGARY']);
        $this->assertEquals('CALGARY', $partial->getAddressString());

        $empty = new Payee();
        $this->assertEquals('', $empty->getAddressString());
    }

    public function testPayeeHasAddress()
    {
        $withAddr = new Payee(['city' => 'AIRDRIE']);
        $this->assertTrue($withAddr->hasAddress());

        $noAddr = new Payee(['name' => 'PAYMENT - THANK YOU']);
        $this->assertFalse($noAddr->hasAddress());
    }

    public function testPayeeToArrayOmitsNulls()
    {
        $payee = new Payee([
            'name' => 'TestMerchant',
            'city' => 'TestCity',
        ]);
        $arr = $payee->toArray();

        $this->assertCount(2, $arr);
        $this->assertEquals('TestMerchant', $arr['name']);
        $this->assertEquals('TestCity', $arr['city']);
        $this->assertArrayNotHasKey('state', $arr);
    }

    public function testPayeeJsonRoundTrip()
    {
        $original = new Payee([
            'name'       => 'WAL-MART #1050',
            'city'       => 'AIRDRIE',
            'state'      => 'AB',
            'country'    => 'CAN',
            'postalCode' => 'T4B 3G5',
            'category'   => 'Grocery Stores and Supermarkets',
        ]);

        $json = $original->toJson();
        $this->assertJson($json);

        $decoded = Payee::fromJson($json);
        $this->assertEquals('WAL-MART #1050', $decoded->name);
        $this->assertEquals('AIRDRIE', $decoded->city);
        $this->assertEquals('AB', $decoded->state);
        $this->assertEquals('CAN', $decoded->country);
        $this->assertEquals('T4B 3G5', $decoded->postalCode);
        $this->assertEquals('Grocery Stores and Supermarkets', $decoded->category);
    }

    public function testPayeeFromJsonWithInvalidInput()
    {
        $payee = Payee::fromJson('not-json');
        $this->assertNull($payee->name);
        $this->assertFalse($payee->hasAddress());
    }

    // ──────────────────────────────────────────────
    // Currency
    // ──────────────────────────────────────────────

    public function testCurrencyCreation()
    {
        $cad = new Currency('cad');
        $this->assertEquals('CAD', $cad->code);
        $this->assertNull($cad->exchangeRate);

        $usd = new Currency('USD', 1.35);
        $this->assertEquals('USD', $usd->code);
        $this->assertEquals(1.35, $usd->exchangeRate);
    }

    public function testCurrencyToString()
    {
        $cur = new Currency('RON');
        $this->assertEquals('RON', (string)$cur);
    }

    public function testCurrencyToArray()
    {
        $simple = new Currency('CAD');
        $this->assertEquals(['code' => 'CAD'], $simple->toArray());

        $withRate = new Currency('USD', 1.35);
        $arr = $withRate->toArray();
        $this->assertEquals('USD', $arr['code']);
        $this->assertEquals(1.35, $arr['exchangeRate']);
    }

    // ──────────────────────────────────────────────
    // Balance
    // ──────────────────────────────────────────────

    public function testBalanceCreation()
    {
        $ledger = new Balance(1500.00, Balance::TYPE_LEDGER, '2026-03-20');
        $this->assertEquals(1500.00, $ledger->amount);
        $this->assertEquals(Balance::TYPE_LEDGER, $ledger->type);
        $this->assertEquals('2026-03-20', $ledger->asOfDate);
    }

    public function testBalanceDefaults()
    {
        $bal = new Balance(500.00);
        $this->assertEquals(Balance::TYPE_LEDGER, $bal->type);
        $this->assertNull($bal->asOfDate);
    }

    public function testBalanceConstants()
    {
        $this->assertEquals('LEDGER', Balance::TYPE_LEDGER);
        $this->assertEquals('AVAILABLE', Balance::TYPE_AVAILABLE);
    }

    public function testBalanceToArray()
    {
        $bal = new Balance(1234.56, Balance::TYPE_AVAILABLE, '2026-01-15');
        $arr = $bal->toArray();

        $this->assertEquals(1234.56, $arr['amount']);
        $this->assertEquals('AVAILABLE', $arr['type']);
        $this->assertEquals('2026-01-15', $arr['asOfDate']);
    }

    // ──────────────────────────────────────────────
    // Transaction payeeData integration
    // ──────────────────────────────────────────────

    public function testTransactionSetPayee()
    {
        $tx = new Transaction(['date' => '2026-01-01', 'amount' => -94.59]);

        $payee = new Payee([
            'name' => 'WAL-MART #1050',
            'city' => 'AIRDRIE',
            'state' => 'AB',
        ]);

        $tx->setPayee($payee);

        $this->assertNotNull($tx->payeeData);
        $this->assertJson($tx->payeeData);
        $this->assertEquals('WAL-MART #1050', $tx->payee);
    }

    public function testTransactionSetPayeeDoesNotOverwriteExistingPayee()
    {
        $tx = new Transaction(['date' => '2026-01-01', 'payee' => 'EXISTING PAYEE']);

        $payee = new Payee(['name' => 'NEW NAME']);
        $tx->setPayee($payee);

        $this->assertEquals('EXISTING PAYEE', $tx->payee);
        $this->assertNotNull($tx->payeeData);
    }

    public function testTransactionGetPayeeRoundTrip()
    {
        $tx = new Transaction();
        $original = new Payee([
            'name'       => 'WAL-MART #1050',
            'city'       => 'AIRDRIE',
            'state'      => 'AB',
            'country'    => 'CAN',
            'postalCode' => 'T4B 3G5',
            'category'   => 'Grocery Stores and Supermarkets',
        ]);

        $tx->setPayee($original);

        $retrieved = $tx->getPayee();
        $this->assertInstanceOf(Payee::class, $retrieved);
        $this->assertEquals('WAL-MART #1050', $retrieved->name);
        $this->assertEquals('AIRDRIE', $retrieved->city);
        $this->assertEquals('Grocery Stores and Supermarkets', $retrieved->category);
    }

    public function testTransactionGetPayeeReturnsNullWhenEmpty()
    {
        $tx = new Transaction();
        $this->assertNull($tx->getPayee());
    }

    public function testTransactionPayeeDataViaConstructor()
    {
        $payeeJson = json_encode(['name' => 'Test', 'city' => 'Calgary']);
        $tx = new Transaction(['payeeData' => $payeeJson]);

        $this->assertEquals($payeeJson, $tx->payeeData);
        $p = $tx->getPayee();
        $this->assertEquals('Test', $p->name);
        $this->assertEquals('Calgary', $p->city);
    }

    // ──────────────────────────────────────────────
    // Statement new entity properties
    // ──────────────────────────────────────────────

    public function testStatementBankAccountProperty()
    {
        $stmt = new Statement();
        $this->assertNull($stmt->bankAccount);

        $acct = new BankAccount(['accountId' => '123', 'accountType' => BankAccount::TYPE_CHECKING]);
        $stmt->bankAccount = $acct;

        $this->assertInstanceOf(BankAccount::class, $stmt->bankAccount);
        $this->assertEquals('123', $stmt->bankAccount->accountId);
    }

    public function testStatementCurrencyProperty()
    {
        $stmt = new Statement();
        $this->assertNull($stmt->defaultCurrency);

        $stmt->defaultCurrency = new Currency('CAD');
        $this->assertEquals('CAD', (string)$stmt->defaultCurrency);
    }

    public function testStatementBalanceProperties()
    {
        $stmt = new Statement();
        $this->assertNull($stmt->ledgerBalance);
        $this->assertNull($stmt->availableBalance);

        $stmt->ledgerBalance = new Balance(5000.00, Balance::TYPE_LEDGER, '2026-03-20');
        $stmt->availableBalance = new Balance(4800.00, Balance::TYPE_AVAILABLE, '2026-03-20');

        $this->assertEquals(5000.00, $stmt->ledgerBalance->amount);
        $this->assertEquals(4800.00, $stmt->availableBalance->amount);
    }

    public function testStatementBackwardCompatibility()
    {
        $stmt = new Statement([
            'bankName'       => 'TD',
            'accountNumber'  => '1234567890',
            'currency'       => 'CAD',
            'openingBalance' => 1000.00,
            'closingBalance' => 2000.00,
        ]);

        // Old flat fields still work
        $this->assertEquals('TD', $stmt->bankName);
        $this->assertEquals('1234567890', $stmt->accountNumber);
        $this->assertEquals('CAD', $stmt->currency);
        $this->assertEquals(1000.00, $stmt->openingBalance);
        $this->assertEquals(2000.00, $stmt->closingBalance);

        // New entity fields default to null
        $this->assertNull($stmt->bankAccount);
        $this->assertNull($stmt->defaultCurrency);
        $this->assertNull($stmt->ledgerBalance);
        $this->assertNull($stmt->availableBalance);
    }
}
