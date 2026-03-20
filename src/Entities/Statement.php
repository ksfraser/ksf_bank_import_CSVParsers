<?php
/**
 * Statement Entity
 *
 * Models a bank statement containing multiple transactions.
 * Maps to bi_statements_model structure in ksf_bank_import.
 * 
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Entities;

class Statement
{
    /** @var string|null Bank name or provider */
    public $bankName;

    /** @var string|null Account number or identifier (backward-compatible flat field) */
    public $accountNumber;

    /** @var string|null Currency code (e.g., USD, CAD, RON) — backward-compatible flat field */
    public $currency;

    /** @var string|null Start date of the statement (Y-m-d) */
    public $startDate;

    /** @var string|null End date (Y-m-d) */
    public $endDate;

    /** @var float|null Balance at the start of the period */
    public $openingBalance;

    /** @var float|null Balance at the end of the period */
    public $closingBalance;

    /**
     * Structured bank account entity (OFX BANKACCTFROM).
     *
     * @var BankAccount|null
     * @requirement REQ-004: OFX-aligned entity model for BankAccount
     */
    public $bankAccount;

    /**
     * Statement default currency entity.
     *
     * @var Currency|null
     * @requirement REQ-004: OFX-aligned entity model for Currency
     */
    public $defaultCurrency;

    /**
     * Ledger balance at statement close (OFX LEDGERBAL).
     *
     * @var Balance|null
     * @requirement REQ-004: OFX-aligned entity model for Balance
     */
    public $ledgerBalance;

    /**
     * Available balance at statement close (OFX AVAILBAL).
     *
     * @var Balance|null
     * @requirement REQ-004: OFX-aligned entity model for Balance
     */
    public $availableBalance;

    /** @var Transaction[] List of transactions in this statement */
    protected $transactions = [];

    /**
     * @param array $data Initialization data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Add a transaction to the statement.
     * 
     * @param Transaction $transaction
     */
    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * Aggregate total of credits (+ve) and debits (-ve).
     * 
     * @return array ['credits' => float, 'debits' => float]
     */
    public function getTotals(): array
    {
        $credits = 0.0;
        $debits = 0.0;
        foreach ($this->transactions as $tx) {
            if ($tx->amount > 0) {
                $credits += $tx->amount;
            } else {
                $debits += abs($tx->amount);
            }
        }
        return ['credits' => $credits, 'debits' => $debits];
    }
}
