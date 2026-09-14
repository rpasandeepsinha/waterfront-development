<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Services;

use Carbon\CarbonImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(PriceService::class)]
class PriceServiceTest extends IntegrationTestCase
{
    private PriceService $priceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceService = self::resolve(PriceService::class);
    }

    #[DataProvider('upgradePriceDataProvider')]
    #[Test]
    public function calculateProRatePrice(
        int $fromProductPrice,
        int $toProductPrice,
        int $expectedPrice,
        string $startDate,
        string $nextBillingDate,
        string $testDate,
    ): void {
        $startDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $startDate);
        $nextBillingDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $nextBillingDate);
        CarbonImmutable::setTestNow(CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $testDate));
        self::assertInstanceOf(CarbonImmutable::class, $startDate);
        self::assertInstanceOf(CarbonImmutable::class, $nextBillingDate);

        $price = $this->priceService->calculateProRate($fromProductPrice, $toProductPrice, $nextBillingDate, 12);

        self::assertSame($expectedPrice, $price);
    }

    public static function upgradePriceDataProvider(): Iterator
    {
        // Upgrades.
        yield 'Upgrade not in a leap year' => [
            1000,
            2580,
            1403,
            '2023-01-01',
            '2023-12-31',
            '2023-02-10',
        ];
        yield 'Upgrade in a leap year' => [
            1000,
            2580,
            1403,
            '2024-01-01',
            '2024-12-31',
            '2024-02-10',
        ];
        yield 'Upgrade in the 2nd year' => [
            1000,
            2580,
            1403,
            '2023-01-01',
            '2024-12-31',
            '2024-02-10',
        ];
    }
}
