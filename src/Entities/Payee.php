<?php
/**
 * Payee Entity
 *
 * Structured payee/merchant information following OFX PAYEE patterns.
 * Populated from CSV merchant fields and serialized to JSON on Transaction::payeeData
 * for downstream use (e.g., creating FA suppliers/customers).
 *
 * @requirement REQ-003: Entity mapping to core bank import structures
 * @requirement REQ-004: OFX-aligned entity model for Payee
 */

namespace Parsers\Entities;

class Payee
{
    /** @var string|null Merchant/Payee name */
    public $name;

    /** @var string|null Street address line 1 */
    public $address1;

    /** @var string|null City */
    public $city;

    /** @var string|null State or province */
    public $state;

    /** @var string|null Postal code or ZIP */
    public $postalCode;

    /** @var string|null Country code (e.g., CAN, USA, ROU) */
    public $country;

    /** @var string|null Phone number */
    public $phone;

    /** @var string|null Merchant category description */
    public $category;

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
     * Returns a readable one-line address string for memo display.
     *
     * @return string
     */
    public function getAddressString(): string
    {
        $parts = array_filter([
            $this->city,
            $this->state,
            $this->country,
            $this->postalCode,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Serializes all non-null fields to an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * JSON-encode the payee for storage in Transaction::payeeData.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Reconstruct a Payee from a JSON string.
     *
     * @param string $json
     * @return self
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        return new self(is_array($data) ? $data : []);
    }

    /**
     * Returns true if the payee has any non-null address component.
     *
     * @return bool
     */
    public function hasAddress(): bool
    {
        return $this->city !== null
            || $this->state !== null
            || $this->country !== null
            || $this->postalCode !== null;
    }
}
