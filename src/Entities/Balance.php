<?php
/**
 * Balance Entity
 *
 * Models a balance record following OFX LEDGERBAL/AVAILBAL patterns.
 * Tracks both ledger balance and available balance with their effective dates.
 *
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model for Balance
 */

namespace Parsers\Entities;

class Balance
{
    /** @var string Balance type: LEDGER */
    const TYPE_LEDGER = 'LEDGER';

    /** @var string Balance type: AVAILABLE */
    const TYPE_AVAILABLE = 'AVAILABLE';

    /** @var float The balance amount */
    public $amount;

    /** @var string|null Effective date of this balance (Y-m-d) */
    public $asOfDate;

    /** @var string Balance type (one of the TYPE_ constants) */
    public $type;

    /**
     * @param float $amount
     * @param string $type One of the TYPE_ constants
     * @param string|null $asOfDate Y-m-d format
     */
    public function __construct(float $amount, string $type = self::TYPE_LEDGER, ?string $asOfDate = null)
    {
        $this->amount = $amount;
        $this->type = $type;
        $this->asOfDate = $asOfDate;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'amount'   => $this->amount,
            'type'     => $this->type,
            'asOfDate' => $this->asOfDate,
        ];
    }
}
