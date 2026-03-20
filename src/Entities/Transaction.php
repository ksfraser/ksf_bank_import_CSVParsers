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

use Ksfraser\Contact\DTO\ContactData;

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
     * JSON-encoded structured contact/payee data for downstream use.
     * Contains ContactData DTO fields (name, city, state_province, country, postal_code, tags, etc.)
     * that can be parsed later to create FA suppliers/customers.
     *
     * @var string|null JSON string
     * @requirement REQ-004: OFX-aligned entity model using ksfraser/contact-dto
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
     * Set structured payee data from a ContactData DTO.
     * Stores the JSON representation and keeps payee string in sync.
     *
     * @param ContactData $contact
     */
    public function setPayee(ContactData $contact): void
    {
        $this->payeeData = json_encode($contact->toArray(), JSON_UNESCAPED_UNICODE);
        if ($contact->name !== '' && ($this->payee === null || $this->payee === '')) {
            $this->payee = $contact->name;
        }
    }

    /**
     * Reconstruct a ContactData DTO from the stored JSON payeeData.
     *
     * @return ContactData|null
     */
    public function getPayee(): ?ContactData
    {
        if ($this->payeeData === null) {
            return null;
        }
        $data = json_decode($this->payeeData, true);
        if (!is_array($data)) {
            return null;
        }
        $contact = new ContactData();
        $contact->fromArray($data);
        return $contact;
    }
}
