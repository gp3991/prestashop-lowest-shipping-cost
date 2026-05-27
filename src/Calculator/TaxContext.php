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
 * Decides how the net shipping base becomes the figure shown on the product
 * page, mirroring the tax branch of Cart::getPackageShippingCost():
 *
 *  - PS_TAX off            -> no tax at all (net base is shown);
 *  - storefront shows net  -> net base is shown (e.g. a B2B group, PS_TAX_EXC),
 *                             so the shipping figure matches the product price;
 *  - PS_ATCP_SHIPWRAP (DE) -> the carrier has no own tax rate; the base is
 *                             treated as gross, and net display deduces the
 *                             pre-tax value from the products' tax rate;
 *  - otherwise (B2C)       -> base * (1 + carrier tax rate).
 *
 * Keeping this out of the carrier x zone loop makes every tax mode unit-testable
 * with plain arithmetic, without booting PrestaShop.
 */
final class TaxContext
{
    /** @var bool PS_TAX - tax handling enabled for the shop */
    private $taxEnabled;

    /** @var bool storefront shows tax-included prices for the current group */
    private $displayGross;

    /** @var bool PS_ATCP_SHIPWRAP - shipping taxed at the products' rate (DE) */
    private $atcp;

    /** @var float products' tax rate in percent, used for the ATCP net deduction */
    private $productTaxRate;

    public function __construct(bool $taxEnabled, bool $displayGross, bool $atcp, float $productTaxRate = 0.0)
    {
        $this->taxEnabled = $taxEnabled;
        $this->displayGross = $displayGross;
        $this->atcp = $atcp;
        $this->productTaxRate = $productTaxRate;
    }

    /**
     * Turns the net shipping base into the displayed figure.
     *
     * @param float $netBase               base before tax (ranges + handling + additional)
     * @param float $carrierTaxRatePercent the carrier's own tax rate, in percent
     */
    public function applyTo(float $netBase, float $carrierTaxRatePercent): float
    {
        if (!$this->taxEnabled) {
            return $netBase;
        }

        if ($this->atcp) {
            // Shipping has no own tax rate; the base is already a gross figure.
            if ($this->displayGross) {
                return $netBase;
            }

            return $this->productTaxRate > 0
                ? $netBase / (1 + ($this->productTaxRate / 100))
                : $netBase;
        }

        if (!$this->displayGross) {
            return $netBase;
        }

        return $netBase * (1 + ($carrierTaxRatePercent / 100));
    }
}
