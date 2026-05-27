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

use Cache;
use Configuration;
use Context;
use PrestaShop\Module\LowestShippingCost\Configuration\ConfigurationData;
use Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Caching layer around LowestShippingCostCalculator. Stores each CalculationResult
 * in the PrestaShop Cache, keyed by every input that affects the outcome (product,
 * combination, quantity, destination, currency, shop, customer group) plus a
 * version token that invalidate() bumps to drop all entries at once.
 *
 * The product page re-renders on every quantity/combination change, so memoising
 * the (relatively expensive) carrier x zone scan avoids recomputing identical
 * results within and across requests.
 */
class CachedCalculator
{
    /** @var Context */
    private $context;

    /** @var LowestShippingCostCalculator */
    private $calculator;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->calculator = new LowestShippingCostCalculator($context);
    }

    /**
     * Returns the cached result for these inputs, computing and storing it on a miss.
     *
     * @param array<int, array<string, mixed>>|null $countries
     */
    public function calculate(
        Product $product,
        int $idProductAttribute = 0,
        int $quantity = 1,
        ?array $countries = null,
        bool $allGroups = false,
        bool $skipExternal = true,
    ): CalculationResult {
        $key = $this->buildKey((int) $product->id, $idProductAttribute, $quantity, $countries, $allGroups);
        if (Cache::isStored($key)) {
            $cached = Cache::retrieve($key);
            if ($cached instanceof CalculationResult) {
                return $cached;
            }
        }

        $result = $this->calculator->calculate(
            $product,
            $idProductAttribute,
            $quantity,
            $countries,
            $allGroups,
            $skipExternal
        );
        Cache::store($key, $result);

        return $result;
    }

    /**
     * Drops every cached result by bumping the version embedded in each key.
     */
    public static function invalidate(): void
    {
        Configuration::updateValue(
            ConfigurationData::KEY_CACHE_VERSION,
            (int) Configuration::get(ConfigurationData::KEY_CACHE_VERSION) + 1
        );
    }

    /**
     * Cache key depending on every condition that affects the result: version,
     * product, combination, quantity, currency, shop, customer group and
     * destination (single country vs global scan).
     *
     * @param array<int, array<string, mixed>>|null $countries
     */
    private function buildKey(int $idProduct, int $idProductAttribute, int $quantity, ?array $countries, bool $allGroups): string
    {
        $destToken = 'global';
        if (is_array($countries) && count($countries) === 1) {
            $first = reset($countries);
            if (is_array($first)) {
                $destToken = 'c' . (int) $first['id_country'];
            }
        }

        return implode('_', [
            'lsc',
            (int) Configuration::get(ConfigurationData::KEY_CACHE_VERSION),
            $idProduct,
            $idProductAttribute,
            $quantity,
            (int) $this->context->currency->id,
            (int) $this->context->shop->id,
            $this->currentGroupToken($allGroups),
            $destToken,
        ]);
    }

    private function currentGroupToken(bool $allGroups): string
    {
        if ($allGroups) {
            return 'all';
        }
        $customer = $this->context->customer;
        if ($customer && $customer->id) {
            $groups = array_map('intval', $customer->getGroups());
            sort($groups);

            return 'g' . implode('-', $groups);
        }

        return 'v' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP');
    }
}
