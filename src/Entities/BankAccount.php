<?php
/**
 * BankAccount Entity
 *
 * Models a bank account following OFX BANKACCTFROM/BANKACCTTO patterns.
 * Used by Statement to identify the source account.
 *
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model for BankAccount
 */

namespace Parsers\Entities;

class BankAccount
{
    /** @var string Account type: CHECKING */
    const TYPE_CHECKING = 'CHECKING';

    /** @var string Account type: SAVINGS */
    const TYPE_SAVINGS = 'SAVINGS';

    /** @var string Account type: CREDITLINE (credit card) */
    const TYPE_CREDITLINE = 'CREDITLINE';

    /** @var string Account type: MONEYMRKT (money market) */
    const TYPE_MONEYMRKT = 'MONEYMRKT';

    /** @var string|null Bank identifier (routing number / institution ID) */
    public $bankId;

    /** @var string|null Account identifier (account number) */
    public $accountId;

    /** @var string|null Account type (one of the TYPE_ constants) */
    public $accountType;

    /** @var string|null Branch identifier */
    public $branchId;

    /** @var string|null Account holder name */
    public $accountHolder;

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
     * Returns the full display label for the account.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $parts = array_filter([
            $this->bankId,
            $this->accountId,
            $this->accountType ? '(' . $this->accountType . ')' : null,
        ]);

        return implode(' ', $parts) ?: '(unknown account)';
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'bankId'        => $this->bankId,
            'accountId'     => $this->accountId,
            'accountType'   => $this->accountType,
            'branchId'      => $this->branchId,
            'accountHolder' => $this->accountHolder,
        ];
    }
}
