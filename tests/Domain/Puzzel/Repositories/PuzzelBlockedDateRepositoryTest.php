<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\PuzzelBlockedDateFactory;
use Tests\TestCase;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;

#[CoversClass(PuzzelBlockedDateRepository::class)]
class PuzzelBlockedDateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function getFutureDates(): void
    {
        $repository = new PuzzelBlockedDateRepository();

        // Create past date
        PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->subDay(),
        ]);

        $futureDate = PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->addDay(),
        ]);

        $todayDate = PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->addHour(),
        ]);

        $result = $repository->getTodayAndFutureDates();

        self::assertCount(2, $result);
        self::assertTrue($result->contains('id', $futureDate->id));
        self::assertTrue($result->contains('id', $todayDate->id));
    }

    #[Test]
    public function getFutureDatePagination(): void
    {
        $repository = new PuzzelBlockedDateRepository();

        // Create past date
        PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->subDay(),
        ]);

        $futureDays = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
        $futureDates = PuzzelBlockedDateFactory::new()
            ->state(
                [
                'date' => CarbonImmutable::now()->addDays(array_pop($futureDays)),
                ]
            )
            ->createMany(10);

        $todayDate = PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->addHour(),
        ]);

        $result = $repository->getTodayAndFutureDatesPagination();

        self::assertCount(11, $result);
        self::assertTrue($result->contains('id', $todayDate->id));

        foreach ($futureDates as $futureDate) {
            self::assertTrue($result->contains('id', $futureDate->id));
        }

        $pageSizeResult = $repository->getTodayAndFutureDatesPagination(5);
        self::assertCount(5, $pageSizeResult);
    }

    #[Test]
    public function dateIsBlocked(): void
    {
        $repository = new PuzzelBlockedDateRepository();

        PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::tomorrow()->addHour()->addMinutes(), // Time should be ignored
        ]);

        self::assertTrue($repository->dateIsBlocked(CarbonImmutable::tomorrow()));
        self::assertFalse($repository->dateIsBlocked(CarbonImmutable::today()));
    }
}
