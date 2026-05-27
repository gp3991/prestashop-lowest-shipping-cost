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

use Address;
use Carrier;
use Combination;
use Configuration;
use Context;
use Country;
use Currency;
use Product;
use Tools;
use Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Computes the lowest POSSIBLE shipping cost for a product on the product page.
 *
 * The product page has no cart and no address, so we cannot call
 * Cart::getPackageShippingCost() directly. Instead we mirror its algorithm
 * (PrestaShop core: Cart::getPackageShippingCost in classes/Cart.php) for the
 * selected quantity/combination and scan every admissible combination of conditions:
 *   carrier x country(zone) -> price, keeping the lowest one (tax included).
 *
 * Conditions taken into account: zones/countries, carrier availability per
 * zone, method (weight/price/free) and ranges + range_behavior, free carrier,
 * PS_SHIPPING_FREE_PRICE/WEIGHT thresholds, PS_SHIPPING_HANDLING,
 * additional_shipping_cost, per-country carrier tax, carriers restricted to the
 * product (product_carrier), customer groups (carrier_group), currency, virtual
 * products and multistore. External carriers (shipping_external) are skipped on
 * purpose - their price is computed by their own module.
 */
class LowestShippingCostCalculator
{
    /** @var Context */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * @param Product    $product             the product on the page
     * @param int        $idProductAttribute  selected combination (0 = none)
     * @param int        $quantity            quantity selected on the page
     * @param array|null $countries           destination countries to scan (rows
     *                                         with id_country, id_zone, name);
     *                                         null = all active countries (global minimum)
     * @param bool       $allGroups           consider every customer group (true) or
     *                                         only the current visitor group (false)
     * @param bool       $skipExternal        skip carriers priced by an external module
     */
    public function calculate(
        Product $product,
        int $idProductAttribute = 0,
        int $quantity = 1,
        ?array $countries = null,
        bool $allGroups = false,
        bool $skipExternal = true,
    ): CalculationResult {
        // 1. Virtual products have no shipping.
        if ($product->is_virtual) {
            return CalculationResult::virtualProduct();
        }

        $idLang = (int) $this->context->language->id;
        /** @var Currency $currency */
        $currency = $this->context->currency;
        $precision = $this->getPrecision();
        $quantity = max(1, $quantity);

        // Unit weight = product weight + the selected combination's additive
        // weight impact (matches Cart::getTotalWeight), scaled by quantity.
        $unitWeight = (float) $product->weight;
        if ($idProductAttribute) {
            $combination = new Combination($idProductAttribute);
            if (Validate::isLoadedObject($combination)) {
                $unitWeight += (float) $combination->weight;
            }
        }
        $totalWeight = $unitWeight * $quantity;

        // Gross unit price (quantity-aware: volume/specific prices) in the
        // context currency; the order total drives the free-price threshold and
        // the "by price" method.
        $unitPrice = (float) $product->getPrice(
            true,
            $idProductAttribute ?: null,
            $precision,
            null,
            false,
            true,
            $quantity
        );
        $orderTotal = $unitPrice * $quantity;

        // Additional shipping cost is a product-level field, applied per unit.
        $additionalTotal = (float) $product->additional_shipping_cost * $quantity;

        $candidateIds = $this->getCandidateCarrierIds($product, $idLang, $allGroups);
        if (empty($candidateIds)) {
            return CalculationResult::unavailable();
        }

        // Global shipping configuration - same keys as Cart::getPackageShippingCost().
        $conf = Configuration::getMultiple([
            'PS_SHIPPING_FREE_PRICE',
            'PS_SHIPPING_HANDLING',
            'PS_SHIPPING_FREE_WEIGHT',
        ]);
        $freePrice = isset($conf['PS_SHIPPING_FREE_PRICE'])
            ? (float) Tools::convertPrice((float) $conf['PS_SHIPPING_FREE_PRICE'], $currency)
            : 0.0;
        $freeWeight = isset($conf['PS_SHIPPING_FREE_WEIGHT']) ? (float) $conf['PS_SHIPPING_FREE_WEIGHT'] : 0.0;
        $handling = isset($conf['PS_SHIPPING_HANDLING']) ? (float) $conf['PS_SHIPPING_HANDLING'] : 0.0;

        // Destination scope: a provided list (visitor / shop default country) or
        // all active countries (global minimum) when none is supplied.
        if ($countries === null) {
            $countries = Country::getCountries($idLang, true);
        }
        $best = null;
        $carriers = []; // per-call cache of Carrier objects

        foreach ($countries as $country) {
            $idZone = (int) $country['id_zone'];
            if (!$idZone) {
                continue;
            }
            // Helper address used only to resolve the carrier tax rate (not persisted).
            $address = new Address();
            $address->id_country = (int) $country['id_country'];

            foreach ($candidateIds as $idCarrier) {
                $idCarrier = (int) $idCarrier;
                // Zone + carrier active + zone active checked in a single query.
                if (!Carrier::checkCarrierZone($idCarrier, $idZone)) {
                    continue;
                }
                if (!isset($carriers[$idCarrier])) {
                    $carriers[$idCarrier] = new Carrier($idCarrier, $idLang);
                }
                $carrier = $carriers[$idCarrier];
                if (!Validate::isLoadedObject($carrier)) {
                    continue;
                }
                if ($skipExternal && $carrier->shipping_external) {
                    continue;
                }

                $entry = $this->computeForCarrierZone(
                    $carrier,
                    $idZone,
                    $totalWeight,
                    $orderTotal,
                    $additionalTotal,
                    $currency,
                    $freePrice,
                    $freeWeight,
                    $handling,
                    $precision,
                    $address
                );
                if ($entry === null) {
                    continue; // carrier not available for this zone/weight/range
                }

                if ($best === null || $entry['cost'] < $best['cost']) {
                    $entry['idCarrier'] = $idCarrier;
                    $entry['carrierName'] = (string) $carrier->name;
                    $entry['idCountry'] = (int) $country['id_country'];
                    $entry['countryName'] = (string) $country['name'];
                    $best = $entry;

                    // A cost of 0 is the absolute minimum - no need to keep searching.
                    if ($best['cost'] <= 0) {
                        break 2;
                    }
                }
            }
        }

        if ($best === null) {
            return CalculationResult::unavailable();
        }
        if ($best['free'] || $best['cost'] <= 0) {
            return CalculationResult::freeShipping(
                $best['idCarrier'],
                $best['carrierName'],
                $best['idCountry'],
                $best['countryName']
            );
        }

        return CalculationResult::ok(
            $best['cost'],
            $best['idCarrier'],
            $best['carrierName'],
            $best['idCountry'],
            $best['countryName']
        );
    }

    /**
     * Shipping cost of the package (selected quantity) for a given carrier and
     * zone (gross).
     *
     * Returns ['cost' => float, 'free' => bool] or null when the carrier is not
     * available for this zone/weight/range. Logic mirrors the computation branch
     * of Cart::getPackageShippingCost().
     */
    private function computeForCarrierZone(
        Carrier $carrier,
        int $idZone,
        float $totalWeight,
        float $orderTotal,
        float $additionalTotal,
        Currency $currency,
        float $freePrice,
        float $freeWeight,
        float $handling,
        int $precision,
        Address $address,
    ): ?array {
        $shippingMethod = (int) $carrier->getShippingMethod();
        $free = false;
        $base = 0.0;

        if ($shippingMethod === Carrier::SHIPPING_METHOD_FREE || $carrier->is_free) {
            $free = true;
        } elseif ($freePrice > 0 && $orderTotal >= $freePrice) {
            $free = true;
        } elseif ($freeWeight > 0 && $totalWeight >= $freeWeight) {
            $free = true;
        } else {
            $idCarrier = (int) $carrier->id;
            if ($shippingMethod === Carrier::SHIPPING_METHOD_WEIGHT) {
                // range_behavior = 1 => "disable carrier when out of range".
                if ($carrier->range_behavior
                    && !Carrier::checkDeliveryPriceByWeight($idCarrier, $totalWeight, $idZone)) {
                    return null;
                }
                $price = $carrier->getDeliveryPriceByWeight($totalWeight, $idZone);
            } else { // SHIPPING_METHOD_PRICE
                if ($carrier->range_behavior
                    && !Carrier::checkDeliveryPriceByPrice($idCarrier, $orderTotal, $idZone, (int) $currency->id)) {
                    return null;
                }
                $price = $carrier->getDeliveryPriceByPrice($orderTotal, $idZone, (int) $currency->id);
            }

            // false/null => no ranges at all defined for this zone.
            if ($price === false || $price === null) {
                return null;
            }

            $base = (float) $price;
            if ($carrier->shipping_handling && $handling > 0) {
                $base += $handling;
            }
            $base += $additionalTotal; // additional cost x quantity
            $base = (float) Tools::convertPrice($base, $currency);
        }

        // Carrier tax depends on the destination country (tax rules group).
        $taxRate = (float) $carrier->getTaxesRate($address);
        $gross = $base * (1 + ($taxRate / 100));
        $gross = (float) Tools::ps_round($gross, $precision);

        return ['cost' => $gross, 'free' => $free];
    }

    /**
     * Ids of carriers eligible for the product: active, available to the given
     * groups, restricted to the carriers assigned to the product (when such a
     * restriction exists).
     *
     * @return int[]
     */
    private function getCandidateCarrierIds(Product $product, int $idLang, bool $allGroups): array
    {
        $groups = $this->resolveGroups($allGroups);
        // All active carriers honoring groups (carrier_group), without the
        // is_module filter - non-external module carriers are computed too.
        $all = Carrier::getCarriers($idLang, true, false, false, $groups, Carrier::ALL_CARRIERS);
        $allIds = array_map(static function ($c): int {
            return (int) $c['id_carrier'];
        }, $all);

        // Restrict to carriers assigned to the product (product_carrier).
        $assigned = $product->getCarriers();
        if (!empty($assigned)) {
            $assignedIds = array_map(static function ($c): int {
                return (int) $c['id_carrier'];
            }, $assigned);
            $allIds = array_values(array_intersect($allIds, $assignedIds));
        }

        return array_values(array_unique($allIds));
    }

    /**
     * Customer groups used to filter carriers.
     *
     * @return array|null null = no filter (all groups)
     */
    private function resolveGroups(bool $allGroups): ?array
    {
        if ($allGroups) {
            return null;
        }
        $customer = $this->context->customer;
        if ($customer && $customer->id) {
            return $customer->getGroups();
        }

        // Guest = "Visitor" group.
        return [(int) Configuration::get('PS_UNIDENTIFIED_GROUP')];
    }

    private function getPrecision(): int
    {
        if (method_exists($this->context, 'getComputingPrecision')) {
            return (int) $this->context->getComputingPrecision();
        }

        return (int) $this->context->currency->precision;
    }
}
