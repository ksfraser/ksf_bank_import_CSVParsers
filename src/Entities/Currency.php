<?php
/**
 * Currency Entity
 *
 * Models currency information following OFX CURRENCY/ORIGCURRENCY patterns.
 * Tracks both the statement's default currency and per-transaction original currencies.
 *
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model for Currency
 */

namespace Parsers\Entities;

class Currency
{
    /** @var string ISO-4217 currency code (e.g., CAD, USD, RON, EUR) */
    public $code;

    /** @var float|null Exchange rate relative to the statement default currency */
    public $exchangeRate;

    /**
     * @param string $code ISO-4217 currency code
     * @param float|null $exchangeRate Exchange rate (null = same as default)
     */
    public function __construct(string $code, ?float $exchangeRate = null)
    {
        $this->code = strtoupper($code);
        $this->exchangeRate = $exchangeRate;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->code;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $result = ['code' => $this->code];
        if ($this->exchangeRate !== null) {
            $result['exchangeRate'] = $this->exchangeRate;
        }
        return $result;
    }
}
