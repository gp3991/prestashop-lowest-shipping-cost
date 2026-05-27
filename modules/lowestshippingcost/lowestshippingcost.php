<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * Displays the lowest possible delivery cost on the product page. The
 * calculation (a mirror of the Cart::getPackageShippingCost algorithm), input
 * resolution and result caching all live in src/; this class is a thin adapter
 * that wires the hooks to those services.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\Module\LowestShippingCost\Calculator\CachedCalculator;
use PrestaShop\Module\LowestShippingCost\Calculator\CalculationResult;
use PrestaShop\Module\LowestShippingCost\Configuration\ConfigurationData;
use PrestaShop\Module\LowestShippingCost\Resolver\ProductPageResolver;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class LowestShippingCost extends Module
{
    // Configuration keys and destination-mode values live in ConfigurationData
    // (src/Configuration) as the single source of truth; the front-office hook
    // here and the admin controller both read/write through the same constants.

    /**
     * Hidden admin tab backing the Symfony configuration route. Required so the
     * back-office permission system grants access to the route's
     * _legacy_controller. Auto-installed/removed by parent::install/uninstall.
     *
     * @var array
     */
    public $tabs = [
        [
            'name' => 'Lowest Shipping Cost',
            'class_name' => 'AdminLowestShippingCostConfiguration',
            'parent_class_name' => 'AdminParentModulesSf',
            'visible' => false,
        ],
    ];

    public function __construct()
    {
        $this->name = 'lowestshippingcost';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Recruitment Task';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Lowest Shipping Cost', [], 'Modules.Lowestshippingcost.Admin');
        $this->description = $this->trans('Displays the lowest possible delivery cost on the product page, accounting for carriers, zones, ranges, free-shipping rules, handling, additional cost and tax.', [], 'Modules.Lowestshippingcost.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Lowestshippingcost.Admin');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayHeader')
            && $this->registerHook('actionObjectCarrierUpdateAfter')
            && $this->registerHook('actionObjectCarrierDeleteAfter')
            && $this->registerHook('actionObjectProductUpdateAfter')
            && Configuration::updateValue(ConfigurationData::KEY_ALL_GROUPS, 0)
            && Configuration::updateValue(ConfigurationData::KEY_SKIP_EXTERNAL, 1)
            && Configuration::updateValue(ConfigurationData::KEY_SHOW_DETAILS, 1)
            && Configuration::updateValue(ConfigurationData::KEY_DEST_MODE, ConfigurationData::DEST_VISITOR)
            && Configuration::updateValue(ConfigurationData::KEY_CACHE_VERSION, 1);
    }

    public function uninstall(): bool
    {
        return Configuration::deleteByName(ConfigurationData::KEY_ALL_GROUPS)
            && Configuration::deleteByName(ConfigurationData::KEY_SKIP_EXTERNAL)
            && Configuration::deleteByName(ConfigurationData::KEY_SHOW_DETAILS)
            && Configuration::deleteByName(ConfigurationData::KEY_DEST_MODE)
            && Configuration::deleteByName(ConfigurationData::KEY_CACHE_VERSION)
            && parent::uninstall();
    }

    /**
     * Block rendered inside the price area (theme partial product-prices.tpl), on
     * the product page and inside the Quick View modal.
     *
     * displayProductPriceBlock is used because product-prices.tpl is included by
     * both product.tpl and hummingbird's quickview.tpl, and the core re-renders the
     * whole product_prices fragment (a clean node replacement) on every
     * quantity/variant change via ProductController::displayAjaxRefresh() - so a
     * single hook covers both contexts with live updates and no custom JS. The hook
     * fires several times per page (once per price-block "type"); we render only at
     * the block-level "weight" slot of the product sheet (product-prices.tpl), which
     * keeps valid markup (not nested in an inline element) and never matches the
     * listing miniatures, which emit other types/origins - so the block stays off
     * product tiles. Inputs are resolved by ProductPageResolver and the result is
     * computed/cached by CachedCalculator.
     */
    public function hookDisplayProductPriceBlock(array $params): string
    {
        if (($params['type'] ?? null) !== 'weight' || ($params['hook_origin'] ?? null) !== 'product_sheet') {
            return '';
        }

        $resolver = new ProductPageResolver($this->context);
        $product = $resolver->resolveProduct($params);
        if (!Validate::isLoadedObject($product)) {
            return '';
        }

        $config = (new ConfigurationData())->getConfiguration();
        $idProductAttribute = $resolver->resolveProductAttribute($params);
        $quantity = $resolver->resolveQuantity($params);
        $countries = $resolver->resolveDestinationCountries((string) $config['dest_mode']);

        $calculator = new CachedCalculator($this->context);
        $result = $calculator->calculate(
            $product,
            $idProductAttribute,
            $quantity,
            $countries,
            (bool) $config['all_groups'],
            (bool) $config['skip_external']
        );

        // If no carrier yields a price (only external/unavailable carriers), render nothing.
        if ($result->status === CalculationResult::STATUS_UNAVAILABLE) {
            return '';
        }

        $costFormatted = null;
        if ($result->cost !== null) {
            $costFormatted = $this->context->currentLocale->formatPrice(
                $result->cost,
                $this->context->currency->iso_code
            );
        }

        $this->context->smarty->assign([
            'lsc_status' => $result->status,
            'lsc_cost_formatted' => $costFormatted,
            'lsc_carrier_name' => $result->carrierName,
            'lsc_country_name' => $result->countryName,
            'lsc_show_details' => (bool) $config['show_details'],
        ]);

        return $this->fetch('module:lowestshippingcost/views/templates/hook/lowest-shipping-cost.tpl');
    }

    public function hookDisplayHeader(): void
    {
        $this->context->controller->registerStylesheet(
            'module-lowestshippingcost',
            'modules/' . $this->name . '/views/css/lowestshippingcost.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    // Cache invalidation - CachedCalculator::invalidate() bumps the version, dropping all entries at once.
    public function hookActionObjectCarrierUpdateAfter(array $params): void
    {
        CachedCalculator::invalidate();
    }

    public function hookActionObjectCarrierDeleteAfter(array $params): void
    {
        CachedCalculator::invalidate();
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        CachedCalculator::invalidate();
    }

    /**
     * The "Configure" button in the module list redirects to the Symfony
     * configuration page (see config/routes.yml + src/Controller/Admin).
     */
    public function getContent(): void
    {
        Tools::redirectAdmin(
            SymfonyContainer::getInstance()->get('router')->generate('lowestshippingcost_configuration')
        );
    }
}
