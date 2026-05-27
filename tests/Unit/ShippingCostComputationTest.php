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

use Address;
use Carrier;
use Context;
use Currency;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\LowestShippingCost\Calculator\LowestShippingCostCalculator;
use ReflectionMethod;

/**
 * Exercises the core pricing branch of the calculator
 * (computeForCarrierZone), the part that mirrors Cart::getPackageShippingCost.
 * The PrestaShop core dependencies are replaced by the test doubles defined in
 * tests/bootstrap.php, so every branch is deterministic.
 */
class ShippingCostComputationTest extends TestCase
{
    protected function setUp(): void
    {
        Carrier::$checkByWeight = true;
        Carrier::$checkByPrice = true;
    }

    /**
     * Calls the private computeForCarrierZone() with sensible defaults that
     * each test overrides through $opts.
     *
     * @return array|null ['cost' => float, 'free' => bool] or null
     */
    private function compute(Carrier $carrier, array $opts = []): ?array
    {
        $calculator = new LowestShippingCostCalculator(new Context());
        $method = new ReflectionMethod($calculator, 'computeForCarrierZone');
        $method->setAccessible(true);

        return $method->invoke(
            $calculator,
            $carrier,
            (int) ($opts['idZone'] ?? 1),
            (float) ($opts['totalWeight'] ?? 1.0),
            (float) ($opts['orderTotal'] ?? 100.0),
            (float) ($opts['additionalTotal'] ?? 0.0),
            new Currency(),
            (float) ($opts['freePrice'] ?? 0.0),
            (float) ($opts['freeWeight'] ?? 0.0),
            (float) ($opts['handling'] ?? 0.0),
            (int) ($opts['precision'] ?? 2),
            new Address()
        );
    }

    public function testFreeCarrierFlagYieldsZeroCostRegardlessOfTax(): void
    {
        $carrier = new Carrier();
        $carrier->is_free = true;
        $carrier->taxRate = 23.0;
        $carrier->priceByWeight = 99.0;

        $result = $this->compute($carrier);

        $this->assertTrue($result['free']);
        $this->assertSame(0.0, $result['cost']);
    }

    public function testFreeShippingMethodYieldsZeroCost(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_FREE;

        $result = $this->compute($carrier);

        $this->assertTrue($result['free']);
        $this->assertSame(0.0, $result['cost']);
    }

    public function testFreeWhenOrderReachesFreePriceThreshold(): void
    {
        $carrier = new Carrier();
        $carrier->priceByWeight = 10.0;

        $result = $this->compute($carrier, ['orderTotal' => 100.0, 'freePrice' => 50.0]);

        $this->assertTrue($result['free']);
        $this->assertSame(0.0, $result['cost']);
    }

    public function testFreeWhenWeightReachesFreeWeightThreshold(): void
    {
        $carrier = new Carrier();
        $carrier->priceByWeight = 10.0;

        $result = $this->compute($carrier, ['totalWeight' => 10.0, 'freeWeight' => 5.0]);

        $this->assertTrue($result['free']);
        $this->assertSame(0.0, $result['cost']);
    }

    public function testWeightMethodAppliesTax(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->priceByWeight = 10.0;
        $carrier->taxRate = 23.0;

        $result = $this->compute($carrier);

        $this->assertFalse($result['free']);
        $this->assertEqualsWithDelta(12.30, $result['cost'], 0.001);
    }

    public function testPriceMethodWithoutTax(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_PRICE;
        $carrier->priceByPrice = 8.5;
        $carrier->taxRate = 0.0;

        $result = $this->compute($carrier);

        $this->assertEqualsWithDelta(8.5, $result['cost'], 0.001);
    }

    public function testHandlingIsAddedWhenEnabled(): void
    {
        $carrier = new Carrier();
        $carrier->priceByWeight = 10.0;
        $carrier->shipping_handling = true;

        $result = $this->compute($carrier, ['handling' => 2.5]);

        $this->assertEqualsWithDelta(12.5, $result['cost'], 0.001);
    }

    public function testAdditionalShippingCostIsAdded(): void
    {
        $carrier = new Carrier();
        $carrier->priceByWeight = 10.0;

        $result = $this->compute($carrier, ['additionalTotal' => 3.0]);

        $this->assertEqualsWithDelta(13.0, $result['cost'], 0.001);
    }

    public function testGrossIsRoundedToPrecision(): void
    {
        $carrier = new Carrier();
        $carrier->priceByWeight = 9.99;
        $carrier->taxRate = 23.0; // 9.99 * 1.23 = 12.2877 -> 12.29

        $result = $this->compute($carrier, ['precision' => 2]);

        $this->assertSame(12.29, $result['cost']);
    }

    public function testWeightOutOfRangeWithDisableBehaviorReturnsNull(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->range_behavior = 1;
        Carrier::$checkByWeight = false;

        $this->assertNull($this->compute($carrier));
    }

    public function testPriceOutOfRangeWithDisableBehaviorReturnsNull(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_PRICE;
        $carrier->range_behavior = 1;
        Carrier::$checkByPrice = false;

        $this->assertNull($this->compute($carrier));
    }

    public function testNoRangeDefinedReturnsNull(): void
    {
        $carrier = new Carrier();
        $carrier->shippingMethod = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->priceByWeight = false; // no range at all for this zone

        $this->assertNull($this->compute($carrier));
    }
}
