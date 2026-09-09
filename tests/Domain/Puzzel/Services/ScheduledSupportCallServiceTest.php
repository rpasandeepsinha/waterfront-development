<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Services;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Http\Faking\MockResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\PuzzelBlockedDateFactory;
use Tests\Factories\PuzzelCallbackRequestFactory;
use Tests\Factories\PuzzelCallbackTimeslotFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Puzzel\Dto\SupportCallTimeslotUsage;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackRequestRepository;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackTimeslotRepository;
use Waterfront\Domain\Puzzel\Services\ScheduledSupportCallService;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\Connectors\PuzzelConnector;
use Waterfront\Infra\PuzzelClient\DTO\AccessPoint;
use Waterfront\Infra\PuzzelClient\PuzzelClient;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueue;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueues;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;

#[CoversClass(ScheduledSupportCallService::class)]
class ScheduledSupportCallServiceTest extends IntegrationTestCase
{
    private Customer $customer;

    private PuzzelConnector $puzzelConnector;

    protected function setUp(): void
    {
        parent::setUp();

        $puzzelConfig = new ConnectorConfig(
            authUrl: 'https://app.test-puzzel.com',
            apiUrl: 'https://api.test-puzzel.com',
            clientId: '1111111-test-123',
            clientSecret: 'testsecret',
            tenantId: 1111337,
            userId: 1337111111,
            accessPoint: new AccessPoint('0031123456789', 'NO'),
            callbackQueue: 'q_testing_queue',
            retryConfig: new RetryConfig(),
        );

        $this->puzzelConnector = new PuzzelConnector(
            puzzelConfig: $puzzelConfig,
            logger: $this->app->make(LoggerInterface::class),
            logMasker: $this->app->make(JsonLogMasker::class),
            cache: $this->app->make(Repository::class),
        );

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function getScheduleReturnsExistingRequestAndAvailableSlotsForDate(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $date = new CarbonImmutable('2025-12-05 00:00:00');
        $dateKey = $date->format('Y-m-d');

        $slotA = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'capacity' => 2,
                'start_timeslot' => $date->setTime(10, 0),
                'end_timeslot' => $date->setTime(11, 0),
            ])
            ->makeOne();

        $slotB = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'capacity' => 1,
                'start_timeslot' => $date->setTime(12, 0),
                'end_timeslot' => $date->setTime(13, 0),
            ])
            ->makeOne();

        $desiredCallbackTime = $date->setTime(10, 30);

        $existingRequest = PuzzelCallbackRequestFactory::new()
            ->for($this->customer, 'customer')
            ->for($slotA, 'timeslot')
            ->state([
                'desired_callback_time' => $desiredCallbackTime,
                'phone_number' => '+31612345678',
            ])
            ->makeOne();

        $usageRows = new Collection([
            new SupportCallTimeslotUsage(
                date: $dateKey,
                timeslotUuid: $slotA->uuid->toString(),
                requestsCount: 1,
            ),
            new SupportCallTimeslotUsage(
                date: $dateKey,
                timeslotUuid: $slotB->uuid->toString(),
                requestsCount: 1,
            ),
        ]);

        $puzzelCallbackTimeslotRepository = self::mock(PuzzelCallbackTimeslotRepository::class);
        $puzzelCallbackTimeslotRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([$slotA, $slotB]));

        $puzzelCallbackRequestRepository = self::mock(PuzzelCallbackRequestRepository::class);
        $puzzelCallbackRequestRepository
            ->shouldReceive('findFirstFutureForCustomer')
            ->once()
            ->withArgs(fn (Customer $customer): bool => $customer->is($this->customer))
            ->andReturn($existingRequest);

        $puzzelCallbackRequestRepository
            ->shouldReceive('usageByDateAndTimeslot')
            ->once()
            ->withArgs(function (CarbonImmutable $startDate, CarbonImmutable $endDate): bool {
                self::assertSame('2025-12-05', $startDate->toDateString());
                self::assertSame('2025-12-18', $endDate->toDateString());
                return true;
            })
            ->andReturn($usageRows);

        $puzzelBlockedDateRepository = self::mock(PuzzelBlockedDateRepository::class);
        $puzzelBlockedDateRepository
            ->shouldReceive('getTodayAndFutureDates')
            ->once()
            ->andReturn(new Collection());

        $scheduledSupportCallService = new ScheduledSupportCallService(
            $puzzelCallbackTimeslotRepository,
            $puzzelCallbackRequestRepository,
            $puzzelBlockedDateRepository,
            self::resolve(PuzzelClient::class)
        );

        $schedule = $scheduledSupportCallService->getScheduleForCustomer($this->customer);

        self::assertSame($existingRequest, $schedule->existingRequest);

        $slotsByDate = $schedule->slotsByDate;

        self::assertArrayHasKey($dateKey, $slotsByDate);

        $daySlots = $slotsByDate[$dateKey];

        self::assertCount(1, $daySlots);

        $firstSlot = $daySlots->first();
        self::assertInstanceOf(PuzzelCallbackTimeslot::class, $firstSlot);
        self::assertTrue($firstSlot->is($slotA));
    }

    #[Test]
    public function getScheduleExcludesSlotsAtCapacityOrBeforeNowForToday(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-12-05 09:00:00'));

        $mockPuzzelClient = new OAuthMockClient([
            RequestVisualQueues::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
            RequestVisualQueue::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
        ]);

        $this->puzzelConnector->withMockClient($mockPuzzelClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $date = new CarbonImmutable('2025-12-05 00:00:00');
        $dateKey = $date->format('Y-m-d');

        $slotPast = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'capacity' => 5,
                'start_timeslot' => $date->setTime(8, 0),
                'end_timeslot' => $date->setTime(9, 0),
            ])
            ->makeOne();

        $slotFull = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'capacity' => 1,
                'start_timeslot' => $date->setTime(10, 0),
                'end_timeslot' => $date->setTime(11, 0),
            ])
            ->makeOne();

        $usageRows = new Collection([
            new SupportCallTimeslotUsage(
                date: $dateKey,
                timeslotUuid: $slotPast->uuid->toString(),
                requestsCount: 0,
            ),
            new SupportCallTimeslotUsage(
                date: $dateKey,
                timeslotUuid: $slotFull->uuid->toString(),
                requestsCount: 1,
            ),
        ]);

        $puzzelCallbackTimeslotRepository = self::mock(PuzzelCallbackTimeslotRepository::class);
        $puzzelCallbackTimeslotRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([$slotPast, $slotFull]));

        $puzzelCallbackRequestRepository = self::mock(PuzzelCallbackRequestRepository::class);
        $puzzelCallbackRequestRepository
            ->shouldReceive('findFirstFutureForCustomer')
            ->once()
            ->withArgs(fn (Customer $customer): bool => $customer->is($this->customer))
            ->andReturn(null);

        $puzzelCallbackRequestRepository
            ->shouldReceive('usageByDateAndTimeslot')
            ->once()
            ->andReturn($usageRows);

        $puzzelBlockedDateRepository = self::mock(PuzzelBlockedDateRepository::class);
        $puzzelBlockedDateRepository
            ->shouldReceive('getTodayAndFutureDates')
            ->once()
            ->andReturn(new Collection());

        $service = new ScheduledSupportCallService(
            $puzzelCallbackTimeslotRepository,
            $puzzelCallbackRequestRepository,
            $puzzelBlockedDateRepository,
            self::resolve(PuzzelClient::class)
        );

        $schedule = $service->getScheduleForCustomer($this->customer);

        $slotsByDate = $schedule->slotsByDate;

        self::assertArrayNotHasKey($dateKey, $slotsByDate);
        self::assertNotEmpty($slotsByDate);
    }

    #[Test]
    public function getScheduleExcludesBlockedDates(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $date = $now->nextWeekday();
        $dateKey = $date->format('Y-m-d');

        $blockedDate = PuzzelBlockedDateFactory::new()
            ->state([
                'date' => $date,
            ])
            ->makeOne();

        $blockedDate2 = PuzzelBlockedDateFactory::new()
            ->state([
                'date' => $date->addDays(2)->nextWeekday(),
            ])
            ->makeOne();

        $slot = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'capacity' => 2,
                'start_timeslot' => $date->setTime(10, 0),
                'end_timeslot' => $date->setTime(11, 0),
            ])
            ->makeOne();

        $mockPuzzelClient = new OAuthMockClient([
            RequestVisualQueues::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
            RequestVisualQueue::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
        ]);

        $this->puzzelConnector->withMockClient($mockPuzzelClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $puzzelCallbackTimeslotRepository = self::mock(PuzzelCallbackTimeslotRepository::class);
        $puzzelCallbackTimeslotRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([$slot]));

        $puzzelCallbackRequestRepository = self::mock(PuzzelCallbackRequestRepository::class);
        $puzzelCallbackRequestRepository
            ->shouldReceive('findFirstFutureForCustomer')
            ->once()
            ->andReturn(null);

        $puzzelCallbackRequestRepository
            ->shouldReceive('usageByDateAndTimeslot')
            ->once()
            ->andReturn(new Collection());

        $puzzelBlockedDateRepository = self::mock(PuzzelBlockedDateRepository::class);
        $puzzelBlockedDateRepository
            ->shouldReceive('getTodayAndFutureDates')
            ->once()
            ->andReturn(new Collection([$blockedDate, $blockedDate2]));

        $service = new ScheduledSupportCallService(
            $puzzelCallbackTimeslotRepository,
            $puzzelCallbackRequestRepository,
            $puzzelBlockedDateRepository,
            self::resolve(PuzzelClient::class)
        );

        $schedule = $service->getScheduleForCustomer($this->customer);

        $slotsByDate = $schedule->slotsByDate;

        self::assertArrayNotHasKey($dateKey, $slotsByDate);
    }
}
