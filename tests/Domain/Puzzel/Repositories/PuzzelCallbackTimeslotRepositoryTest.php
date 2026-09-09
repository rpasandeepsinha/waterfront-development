<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Repositories;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\PuzzelCallbackRequestFactory;
use Tests\Factories\PuzzelCallbackTimeslotFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackTimeslotRepository;

#[CoversClass(PuzzelCallbackTimeslotRepository::class)]
class PuzzelCallbackTimeslotRepositoryTest extends IntegrationTestCase
{
    private PuzzelCallbackTimeslotRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(PuzzelCallbackTimeslotRepository::class);
    }

    #[Test]
    public function hasAvailableCapacityReturnsTrueWhenNoRequests(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 3]);

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsTrueWhenRequestsBelowCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 3]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ]);

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsFalseWhenAtCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ]);

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsFalseWhenCapacityIsZero(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 0]);

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityIgnoresPastRequests(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 12:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->subHour(),
            ]);

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsFalseWhenOverCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 2]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->count(3)
            ->state([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ])
            ->createMany();

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityCountsRequestAtExactlyNowAsFuture(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 10:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now(),
            ]);

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsTrueWhenMixedPastAndFutureRequestsBelowCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 12:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 2]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->subHours(2),
            ]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ]);

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsFalseWhenMixedPastAndFutureRequestsAtCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 12:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->subHours(2),
            ]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ]);

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsTrueWhenMultipleFutureRequestsBelowHighCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 5]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->count(4)
            ->state([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ])
            ->createMany();

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityReturnsFalseWhenMultipleFutureRequestsReachHighCapacity(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 5]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->count(5)
            ->state([
                'puzzel_callback_timeslot_id' => $timeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ])
            ->createMany();

        self::assertFalse($this->repository->hasAvailableCapacity($timeslot));
    }

    #[Test]
    public function hasAvailableCapacityDoesNotCountRequestsFromOtherTimeslots(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $customer = new CustomerFactory()->createOne();

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        $otherTimeslot = PuzzelCallbackTimeslotFactory::new()
            ->createOne(['capacity' => 1]);

        PuzzelCallbackRequestFactory::new()
            ->for($customer, 'customer')
            ->for($otherTimeslot, 'timeslot')
            ->createOne([
                'puzzel_callback_timeslot_id' => $otherTimeslot->id,
                'desired_callback_time' => CarbonImmutable::now()->addHour(),
            ]);

        self::assertTrue($this->repository->hasAvailableCapacity($timeslot));
    }
}
