<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Services;

use Carbon\CarbonImmutable;

class PriceService
{
    /** @phpstan-return non-negative-int */
    public function calculateProRate(int $amountPaid, int $price, CarbonImmutable $until, int $fullPeriodInMonths): int
    {
        $remainingDaysRatio = $this->getRemainingDaysAsRatio($until, $fullPeriodInMonths);
        $proRateAmount = ($price - $amountPaid) * $remainingDaysRatio;
        $proRateAmount = (int) round($proRateAmount);

        return max(0, $proRateAmount);
    }

    /**
     * @param non-negative-int $price
     *
     * @phpstan-return non-negative-int
     */
    public function calculatePercentageDiscount(int $price, float $percentage): int
    {
        assert($percentage >= 0.0 && $percentage <= 100.0);

        $discountFactor = (100 - min($percentage, 100)) / 100;
        $newPrice = (int) round($price * $discountFactor);
        assert($newPrice >= 0);

        return $newPrice;
    }

    private function getRemainingDaysAsRatio(CarbonImmutable $until, int $fullPeriodInMonths): float
    {
        $remainingDays = (int) CarbonImmutable::today()->diffInDays($until, true);
        $fullDays = (int) $until->diffInDays($until->subMonths($fullPeriodInMonths), true);
        $ratio = $remainingDays / $fullDays;

        return min($ratio, 1.0);
    }
}
