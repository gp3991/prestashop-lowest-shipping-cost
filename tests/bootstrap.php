<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * PHPUnit bootstrap: defines the minimal PrestaShop core test doubles the
 * calculator depends on, so its pricing algorithm can be unit-tested in
 * isolation, without booting a full PrestaShop instance.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.1.0');
}

if (!class_exists('Context')) {
    /** Minimal stand-in: the calculator only stores it in the constructor. */
    class Context
    {
    }
}

if (!class_exists('Address')) {
    /** Helper address: only id_country is read (for the tax rate lookup). */
    class Address
    {
        /** @var int */
        public $id_country = 0;
    }
}

if (!class_exists('Currency')) {
    class Currency
    {
        /** @var int */
        public $id = 1;
        /** @var int */
        public $precision = 2;
    }
}

if (!class_exists('Tools')) {
    /** Deterministic money helpers (identity conversion, native rounding). */
    class Tools
    {
        public static function convertPrice($price, $currency = null)
        {
            return $price;
        }

        public static function ps_round($value, $precision = 0)
        {
            return round((float) $value, (int) $precision);
        }
    }
}

if (!class_exists('Carrier')) {
    /**
     * Configurable carrier double. Instance properties drive a single
     * computation; the static flags drive the range_behavior checks.
     */
    class Carrier
    {
        public const SHIPPING_METHOD_DEFAULT = 0;
        public const SHIPPING_METHOD_WEIGHT = 1;
        public const SHIPPING_METHOD_PRICE = 2;
        public const SHIPPING_METHOD_FREE = 3;
        public const ALL_CARRIERS = 5;

        public $id = 1;
        public $name = 'Test carrier';
        public $is_free = false;
        public $range_behavior = 0;
        public $shipping_handling = false;
        public $shipping_external = false;

        /** @var int one of the SHIPPING_METHOD_* constants */
        public $shippingMethod = self::SHIPPING_METHOD_WEIGHT;
        /** @var float|false price returned by the weight method (false = no range) */
        public $priceByWeight = 0.0;
        /** @var float|false price returned by the price method (false = no range) */
        public $priceByPrice = 0.0;
        /** @var float carrier tax rate in percent */
        public $taxRate = 0.0;

        /** @var bool result of the weight range_behavior check */
        public static $checkByWeight = true;
        /** @var bool result of the price range_behavior check */
        public static $checkByPrice = true;

        public function getShippingMethod()
        {
            return $this->shippingMethod;
        }

        public function getDeliveryPriceByWeight($weight, $idZone)
        {
            return $this->priceByWeight;
        }

        public function getDeliveryPriceByPrice($orderTotal, $idZone, $idCurrency)
        {
            return $this->priceByPrice;
        }

        public function getTaxesRate($address)
        {
            return $this->taxRate;
        }

        public static function checkDeliveryPriceByWeight($idCarrier, $weight, $idZone)
        {
            return self::$checkByWeight;
        }

        public static function checkDeliveryPriceByPrice($idCarrier, $orderTotal, $idZone, $idCurrency)
        {
            return self::$checkByPrice;
        }
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
