<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Puzzel;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\SaloonException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\PuzzelCallbackRequestFactory;
use Tests\Factories\PuzzelCallbackTimeslotFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\ScheduledSupportCallController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Puzzel\Dto\SupportCallSchedule;
use Waterfront\Domain\Puzzel\Services\ScheduledSupportCallService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\Connectors\PuzzelConnector;
use Waterfront\Infra\PuzzelClient\DTO\AccessPoint;
use Waterfront\Infra\PuzzelClient\Requests\CreateCallback;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueue;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueues;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;

#[CoversClass(ScheduledSupportCallController::class)]
class ScheduledSupportCallControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private PuzzelConnector $puzzelConnector;

    private Subscription $servicePlusSubscription;

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

        $this->servicePlusSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->for($this->customer)
            ->for(
                new ProductFactory()
                ->hostingBrons()
                ->withServicePlus()
            )
            ->createOne();

        MockClient::destroyGlobal();
    }

    #[Test]
    public function timeslotsReturnsExistingRequestAndAvailableSlots(): void
    {
        $date = new CarbonImmutable('2025-12-05 00:00:00');
        $dateKey = $date->format('Y-m-d');

        $firstStart = $date->setTime(10, 0);
        $firstEnd = $date->setTime(10, 30);
        $secondStart = $date->setTime(11, 0);
        $secondEnd = $date->setTime(11, 30);

        $firstSlot = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'start_timeslot' => $firstStart,
                'end_timeslot' => $firstEnd,
            ])
            ->makeOne();

        $secondSlot = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'start_timeslot' => $secondStart,
                'end_timeslot' => $secondEnd,
            ])
            ->makeOne();

        $desiredCallbackTime = $date->setTime(10, 15);

        $existingRequest = PuzzelCallbackRequestFactory::new()
            ->for($this->customer, 'customer')
            ->for($firstSlot, 'timeslot')
            ->state([
                'desired_callback_time' => $desiredCallbackTime,
                'phone_number' => '+31612345678',
            ])
            ->makeOne();

        $existingRequest->setRelation('timeslot', $firstSlot);

        $dateKey = '2025-12-05';

        $schedule = new SupportCallSchedule(
            existingRequest: $existingRequest,
            slotsByDate: [
                $dateKey => new Collection([$firstSlot, $secondSlot]),
            ],
        );

        $scheduledSupportCallService = self::mock(ScheduledSupportCallService::class);
        $scheduledSupportCallService
            ->shouldReceive('getScheduleForCustomer')
            ->once()
            ->withArgs(fn (Customer $customer): bool => $customer->is($this->customer))
            ->andReturn($schedule);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.puzzel.support-call.time-slots'),
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'existingRequest' => [
                        'date' => $desiredCallbackTime->toIso8601String(),
                        'startTime' => $firstStart->format('H:i'),
                        'endTime' => $firstEnd->format('H:i'),
                        'phoneNumber' => $existingRequest->phone_number,
                    ],
                    'availableSlots' => [
                        $dateKey => [
                            [
                                'uuid' => $firstSlot->uuid->toString(),
                                'display' => sprintf(
                                    '%s - %s',
                                    $firstStart->format('H:i'),
                                    $firstEnd->format('H:i'),
                                ),
                            ],
                            [
                                'uuid' => $secondSlot->uuid->toString(),
                                'display' => sprintf(
                                    '%s - %s',
                                    $secondStart->format('H:i'),
                                    $secondEnd->format('H:i'),
                                ),
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function timeslotsReturnsNullExistingRequestWhenNonePresent(): void
    {
        $startTimeslot = new CarbonImmutable('2025-12-05 14:00:00');
        $endTimeslot = new CarbonImmutable('2025-12-05 15:00:00');

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'start_timeslot' => $startTimeslot,
                'end_timeslot' => $endTimeslot,
            ])
            ->makeOne();

        $dateKey = '2025-12-05';

        $schedule = new SupportCallSchedule(
            existingRequest: null,
            slotsByDate: [
                $dateKey => new Collection([$timeslot]),
            ],
        );

        $scheduledSupportCallService = self::mock(ScheduledSupportCallService::class);
        $scheduledSupportCallService
            ->shouldReceive('getScheduleForCustomer')
            ->once()
            ->withArgs(fn (Customer $customer): bool => $customer->is($this->customer))
            ->andReturn($schedule);

        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.puzzel.support-call.time-slots'),
            )
            ->assertOk()
            ->assertJson([
                'data' => [
                    'existingRequest' => null,
                    'availableSlots' => [
                        $dateKey => [
                            [
                                'uuid' => $timeslot->uuid->toString(),
                                'display' => sprintf(
                                    '%s - %s',
                                    $startTimeslot->format('H:i'),
                                    $endTimeslot->format('H:i'),
                                ),
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function timeslotsReturnsTwoWeeksOfAvailableDates(): void
    {
        $now = new CarbonImmutable('2025-12-05 00:00:00');
        CarbonImmutable::setTestNow($now);

        $mockPuzzelClient = new OAuthMockClient([
            RequestVisualQueues::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
            RequestVisualQueue::class => MockResponse::make('{"result": [], "code": 0, "id": "550e8400-e29b-41d4-a716-446655440000", "message": "OK"}'),
        ]);

        $this->puzzelConnector->withMockClient($mockPuzzelClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        PuzzelCallbackTimeslotFactory::new()
            ->state([
                'start_timeslot' => $now->setTime(9, 0),
                'end_timeslot' => $now->setTime(9, 30),
                'capacity' => 5,
            ])
            ->createOne();

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.puzzel.support-call.time-slots'),
            )
            ->assertOk();

        $availableSlots = $response->json('data.availableSlots');

        self::assertIsArray($availableSlots);

        self::assertCount(10, $availableSlots);

        $dates = array_keys($availableSlots);

        self::assertSame('2025-12-05', $dates[0]);
        self::assertSame('2025-12-18', $dates[array_key_last($dates)]);
    }

    #[Test]
    public function timeslotsReturnsExistingCallbackIfStillInPuzzelQueue(): void
    {
        $now = new CarbonImmutable('2025-12-05 00:00:00');
        CarbonImmutable::setTestNow($now);

        $expectedPhone = '004712345678';
        $this->customer->phone_number = '+4712345678'; // Same as json data file
        $this->customer->save();

        $mockResponse = (string) file_get_contents(__DIR__ . '/data/queue-with-item.json');
        $mockResponseQueues = (string) file_get_contents(__DIR__ . '/data/visual-queues.json');

        $mockPuzzelClient = new OAuthMockClient([
            RequestVisualQueues::class => MockResponse::make($mockResponseQueues),
            RequestVisualQueue::class => MockResponse::make(body: $mockResponse),
        ]);

        $this->puzzelConnector->withMockClient($mockPuzzelClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.puzzel.support-call.time-slots'),
            )
            ->assertOk();

        $existingRequestPhone = $response->json('data.existingRequest.phoneNumber');

        self::assertSame($expectedPhone, $existingRequestPhone);
    }

    #[Test]
    public function timeslotsDoNotIncludeSlotWhenCapacityIsFull(): void
    {
        $now = new CarbonImmutable('2025-12-05 00:00:00');
        CarbonImmutable::setTestNow($now);

        $startTimeslot = $now->setTime(10, 0);
        $endTimeslot = $now->setTime(10, 30);
        $dateKey = $now->format('Y-m-d');

        $timeslot = PuzzelCallbackTimeslotFactory::new()
            ->state([
                'start_timeslot' => $startTimeslot,
                'end_timeslot' => $endTimeslot,
                'capacity' => 1,
            ])
            ->createOne();

        PuzzelCallbackRequestFactory::new()
            ->for($this->customer, 'customer')
            ->for($timeslot, 'timeslot')
            ->state([
                'desired_callback_time' => $now->setTime(10, 15),
            ])
            ->createOne();

        $response = $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.puzzel.support-call.time-slots'),
            )
            ->assertOk();

        $availableSlots = $response->json('data.availableSlots');

        self::assertIsArray($availableSlots);

        self::assertArrayHasKey($dateKey, $availableSlots);

        $slotsForDate = $availableSlots[$dateKey];
        self::assertIsArray($slotsForDate);

        $uuidsForDate = array_column($slotsForDate, 'uuid');

        self::assertNotContains($timeslot->uuid->toString(), $uuidsForDate);
    }

    #[Test]
    public function createScheduledCallbackSuccess(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => CreateCallback::REDIRECT_OK]),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'success',
                    'message' => 'puzzel.callback-created',
                ],
            ]);

        self::assertDatabaseHas('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackOnlyAllowedWithServicePlus(): void
    {
        $this->servicePlusSubscription->forceDelete();

        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => CreateCallback::REDIRECT_OK]),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'timeSlotUuid' => [
                        'validation.puzzel-no-callback-access',
                    ],
                ],
                'message' => 'validation.puzzel-no-callback-access',
            ]);

        self::assertDatabaseMissing('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackSuccessMissingCategoryOrDescription(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => CreateCallback::REDIRECT_OK]),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'success',
                    'message' => 'puzzel.callback-created',
                ],
            ]);

        self::assertDatabaseHas('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => '',
            'request_description' => '',
        ]);
    }

    #[Test]
    public function createScheduledCallbackSuccessEmptyDescriptionAndCategory(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => CreateCallback::REDIRECT_OK]),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => '',
            'description' => '',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertCreated()
            ->assertJson([
                'data' => [
                    'status' => 'success',
                    'message' => 'puzzel.callback-created',
                ],
            ]);

        self::assertDatabaseHas('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackRedirectToError(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $redirect = sprintf('%s?errorMessage=something+went+wrong+here', CreateCallback::REDIRECT_ERROR);

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => $redirect]),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertServerError()
            ->assertJson([
                'data' => [
                    'status' => 'error',
                    'message' => 'puzzel.callback-not-created',
                ],
            ]);

        self::assertDatabaseMissing('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackTimeSlotFullValidation(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne(['capacity' => 0]);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'timeSlotUuid' => [
                        'validation.puzzel-timeslot-full',
                    ],
                ],
                'message' => 'validation.puzzel-timeslot-full',
            ]);

        self::assertDatabaseMissing('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackExistsValidation(): void
    {
        $callbackRequest = PuzzelCallbackRequestFactory::new()
            ->for($this->customer)
            ->state(['desired_callback_time' => CarbonImmutable::now()->addDay()])
            ->createOne();

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $callbackRequest->timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertUnprocessable()
            ->assertJson([
                'errors' => [
                    'timeSlotUuid' => [
                        'validation.puzzel-existing-request',
                    ],
                ],
                'message' => 'validation.puzzel-existing-request',
            ]);

        self::assertDatabaseMissing('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $callbackRequest->timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }

    #[Test]
    public function createScheduledCallbackErrorException(): void
    {
        $timeslot = PuzzelCallbackTimeslotFactory::new()->createOne();

        $mockClient = new OAuthMockClient([
            CreateCallback::class => MockResponse::make(body: '', status: 301, headers: ['Location' => CreateCallback::REDIRECT_ERROR])->throw(new SaloonException('error')),
        ]);

        $this->puzzelConnector->withMockClient($mockClient);
        $this->app->bind(PuzzelConnector::class, fn () => $this->puzzelConnector);

        $postData = [
            'date' => CarbonImmutable::now()->addDay()->format('Y-m-d'),
            'timeSlotUuid' => $timeslot->uuid,
            'category' => 'test-category',
            'description' => 'test-description',
        ];

        $this->actingAsCustomer($this->customer)
            ->postJson(
                uri: $this->generateRoute('partners.puzzel.support-call.store'),
                data: $postData
            )
            ->assertServerError()
            ->assertJson([
                'data' => [
                    'status' => 'error',
                    'message' => 'puzzel.callback-not-created',
                ],
            ]);

        self::assertDatabaseMissing('puzzel_callback_requests', [
            'customer_id' => $this->customer->id,
            'puzzel_callback_timeslot_id' => $timeslot->id,
            'phone_number' => $this->customer->phone_number,
            'name' => $this->customer->contact_name,
            'request_category' => $postData['category'],
            'request_description' => $postData['description'],
        ]);
    }
}
