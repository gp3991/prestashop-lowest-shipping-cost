<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LowestShippingCost\Calculator\TaxContext;

/**
 * Covers the tax branch of Cart::getPackageShippingCost the calculator mirrors:
 * net vs gross display, PS_TAX disabled, and PS_ATCP_SHIPWRAP. Pure arithmetic,
 * no PrestaShop core needed.
 */
class TaxContextTest extends TestCase
{
    public function testB2cGrossDisplayAppliesCarrierTax(): void
    {
        $ctx = new TaxContext(true, true, false);

        $this->assertEqualsWithDelta(12.3, $ctx->applyTo(10.0, 23.0), 0.0001);
    }

    public function testNetDisplayShowsBaseWithoutTax(): void
    {
        // B2B group (PS_TAX_EXC): shipping figure must match the net product price.
        $ctx = new TaxContext(true, false, false);

        $this->assertSame(10.0, $ctx->applyTo(10.0, 23.0));
    }

    public function testTaxDisabledShowsBaseWithoutTax(): void
    {
        // PS_TAX off: there is no tax in the shop at all.
        $ctx = new TaxContext(false, true, false);

        $this->assertSame(10.0, $ctx->applyTo(10.0, 23.0));
    }

    public function testAtcpGrossTreatsBaseAsGross(): void
    {
        // PS_ATCP_SHIPWRAP: carrier has no own rate, base is already gross.
        $ctx = new TaxContext(true, true, true, 19.0);

        $this->assertSame(10.0, $ctx->applyTo(10.0, 23.0));
    }

    public function testAtcpNetDeducesProductTaxRate(): void
    {
        // ATCP + net display: deduce pre-tax value from the products' tax rate.
        $ctx = new TaxContext(true, false, true, 19.0);

        $this->assertEqualsWithDelta(10.0, $ctx->applyTo(11.9, 23.0), 0.0001);
    }

    public function testAtcpNetWithZeroProductRateKeepsBase(): void
    {
        $ctx = new TaxContext(true, false, true, 0.0);

        $this->assertSame(11.9, $ctx->applyTo(11.9, 23.0));
    }
}
