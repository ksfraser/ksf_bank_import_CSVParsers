<?php
/**
 * Transaction Entity
 *
 * Maps to the bi_transactions_model structure in ksf_bank_import.
 * 
 * @requirement REQ-002: Support for multi-line (split) transactions
 * @requirement REQ-003: Entity mapping to core bank import structures
 */

namespace Parsers\Entities;

class Transaction
{
    /** @var string|null Date of the transaction (Y-m-d) */
    public $date;

    /** @var string|null Merchant or Payee name */
    public $payee;

    /** @var float|null Amount (negative for debits, positive for credits) */
    public $amount;

    /** @var string|null Transaction category or account mapping */
    public $category;

    /** @var string|null Memo or additional description */
    public $memo;

    /** @var string|null Unique transaction ID if available */
    public $transactionId;

    /** @var string|null Check or reference number */
    public $referenceNumber;

    /** @var string|null Transaction Direction (C for Credit, D for Debit) */
    public $transactionDC;

    /** @var string|null Currency/Commodity (e.g., CAD, USD) */
    public $commodity;

    /**
     * JSON-encoded structured payee/merchant data for downstream use.
     * Contains Payee entity fields (name, city, state, country, postalCode, category, etc.)
     * that can be parsed later to create FA suppliers/customers.
     *
     * @var string|null JSON string
     * @requirement REQ-004: OFX-aligned entity model for Payee
     */
    public $payeeData;

    /** @var Transaction[] List of split lines if this is a parent transaction */
    protected $splits = [];

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
     * Add a split line to this transaction.
     * 
     * @param Transaction $split
     */
    public function addSplit(Transaction $split): void
    {
        $this->splits[] = $split;
    }

    /**
     * @return Transaction[]
     */
    public function getSplits(): array
    {
        return $this->splits;
    }

    /**
     * Returns true if this transaction has split lines.
     * 
     * @return bool
     */
    public function isSplit(): bool
    {
        return !empty($this->splits);
    }

    /**
     * Set structured payee data from a Payee entity.
     * Stores the JSON representation and keeps payee string in sync.
     *
     * @param Payee $payee
     */
    public function setPayee(Payee $payee): void
    {
        $this->payeeData = $payee->toJson();
        if ($payee->name !== null && ($this->payee === null || $this->payee === '')) {
            $this->payee = $payee->name;
        }
    }

    /**
     * Reconstruct a Payee entity from the stored JSON payeeData.
     *
     * @return Payee|null
     */
    public function getPayee(): ?Payee
    {
        if ($this->payeeData === null) {
            return null;
        }
        return Payee::fromJson($this->payeeData);
    }
}
