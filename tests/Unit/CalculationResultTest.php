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
use PrestaShop\Module\LowestShippingCost\Calculator\CalculationResult;

class CalculationResultTest extends TestCase
{
    public function testOkCarriesEveryField(): void
    {
        $r = CalculationResult::ok(12.30, 4, 'DHL', 8, 'Poland');

        $this->assertSame(CalculationResult::STATUS_OK, $r->status);
        $this->assertSame(12.30, $r->cost);
        $this->assertSame(4, $r->idCarrier);
        $this->assertSame('DHL', $r->carrierName);
        $this->assertSame(8, $r->idCountry);
        $this->assertSame('Poland', $r->countryName);
    }

    public function testFreeShippingHasZeroCost(): void
    {
        $r = CalculationResult::freeShipping(3, 'Click and collect', 8, 'Poland');

        $this->assertSame(CalculationResult::STATUS_FREE, $r->status);
        $this->assertSame(0.0, $r->cost);
        $this->assertSame(3, $r->idCarrier);
        $this->assertSame('Click and collect', $r->carrierName);
    }

    public function testVirtualProductHasNoCostOrCarrier(): void
    {
        $r = CalculationResult::virtualProduct();

        $this->assertSame(CalculationResult::STATUS_VIRTUAL, $r->status);
        $this->assertNull($r->cost);
        $this->assertNull($r->idCarrier);
        $this->assertNull($r->carrierName);
    }

    public function testUnavailableHasNoCostOrCarrier(): void
    {
        $r = CalculationResult::unavailable();

        $this->assertSame(CalculationResult::STATUS_UNAVAILABLE, $r->status);
        $this->assertNull($r->cost);
        $this->assertNull($r->idCarrier);
    }
}
