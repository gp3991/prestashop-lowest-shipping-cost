<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Calculator;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Result of the lowest shipping cost calculation for a product.
 *
 * Immutable DTO passed from the calculator to the template. The status decides
 * which variant of the block is rendered on the product page.
 */
class CalculationResult
{
    /** A specific lowest cost was computed (> 0). */
    public const STATUS_OK = 'ok';
    /** The cheapest option is free shipping (cost = 0). */
    public const STATUS_FREE = 'free';
    /** Virtual product - no shipping. */
    public const STATUS_VIRTUAL = 'virtual';
    /** No carrier handles the product - nothing to display. */
    public const STATUS_UNAVAILABLE = 'unavailable';

    /** @var string */
    public $status;
    /** @var float|null gross cost in the context currency (null when none) */
    public $cost;
    /** @var int|null */
    public $idCarrier;
    /** @var string|null */
    public $carrierName;
    /** @var int|null */
    public $idCountry;
    /** @var string|null destination country yielding the lowest cost */
    public $countryName;

    private function __construct(string $status)
    {
        $this->status = $status;
        $this->cost = null;
        $this->idCarrier = null;
        $this->carrierName = null;
        $this->idCountry = null;
        $this->countryName = null;
    }

    public static function ok(
        float $cost,
        int $idCarrier,
        string $carrierName,
        int $idCountry,
        string $countryName,
    ): self {
        $r = new self(self::STATUS_OK);
        $r->cost = $cost;
        $r->idCarrier = $idCarrier;
        $r->carrierName = $carrierName;
        $r->idCountry = $idCountry;
        $r->countryName = $countryName;

        return $r;
    }

    public static function freeShipping(
        int $idCarrier,
        string $carrierName,
        int $idCountry,
        string $countryName,
    ): self {
        $r = new self(self::STATUS_FREE);
        $r->cost = 0.0;
        $r->idCarrier = $idCarrier;
        $r->carrierName = $carrierName;
        $r->idCountry = $idCountry;
        $r->countryName = $countryName;

        return $r;
    }

    public static function virtualProduct(): self
    {
        return new self(self::STATUS_VIRTUAL);
    }

    public static function unavailable(): self
    {
        return new self(self::STATUS_UNAVAILABLE);
    }
}
