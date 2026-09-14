<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\MigrateSitebuilderSubscriptions;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions\MigrateBasekitSubscriptionJob;
use Waterfront\Apps\OneOffScripts\MigrateSitebuilderSubscriptions\NovaMigrateBasekitDeploymentsAction;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitSubscriptionRepository;
use Waterfront\Domain\Sitebuilder\Enums\BasekitMigrationEligibility;
use Waterfront\Domain\Sitebuilder\Services\BasekitMigrationService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(NovaMigrateBasekitDeploymentsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaMigrateBasekitDeploymentsActionTest extends IntegrationTestCase
{
    private LoggerInterface&MockInterface $logger;

    /** @var Dispatcher&MockObject */
    private Dispatcher $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        /** @var LoggerInterface&MockInterface $mockLogger */
        $mockLogger = self::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $this->logger = $mockLogger;

        $this->dispatcher = $this->createMock(Dispatcher::class);
    }

    #[Test]
    public function fieldsContainExpectedControlsAndDefaults(): void
    {
        $basekitSubscriptionRepository = self::createMock(BasekitSubscriptionRepository::class);
        $basekitSubscriptionRepository->method('countBasekitSubscriptions')->willReturn(42);

        $action = new NovaMigrateBasekitDeploymentsAction(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            basekitSubscriptionRepository: $basekitSubscriptionRepository,
            basekitMigrationService: self::createStub(BasekitMigrationService::class),
        );

        $fields = $action->fields(NovaRequest::create('/'));

        self::assertNotEmpty($fields);
        $serializedFields = array_map(static fn ($field) => $field->jsonSerialize(), $fields);

        $dryRunField = new Collection($serializedFields)->firstWhere('attribute', 'dry-run');
        self::assertIsArray($dryRunField, 'dry-run field should be present');
        self::assertSame('dry-run', $dryRunField['attribute'] ?? null);

        $limitField = new Collection($serializedFields)->firstWhere('attribute', 'limit');
        self::assertIsArray($limitField, 'limit field should be present');
        self::assertSame('limit', $limitField['attribute'] ?? null);
        $enforcesMinimumZero =
            ($limitField['min'] ?? null) === 0
            || array_key_exists('rules', $limitField) && in_array('min:0', (array) $limitField['rules'], true);
        self::assertTrue($enforcesMinimumZero, 'limit should enforce min=0');

        $eligibleEstimateField = new Collection($serializedFields)->firstWhere(
            'name',
            'Eligible subscriptions (estimate)',
        );
        self::assertIsArray($eligibleEstimateField, 'estimate field should be present');
        self::assertTrue((bool) ($eligibleEstimateField['readonly'] ?? false), 'estimate should be readonly');
    }

    #[Test]
    public function handleDryRunCountsEligibility_NoJobsQueued(): void
    {
        $subscriptions = new Collection([
            (function () {
                $subscription = new Subscription();
                $subscription->id = 1;

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->id = 2;

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->id = 3;

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->id = 4;

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->id = 5;

                return $subscription;
            })(),
        ]);

        $basekitSubscriptionRepository = self::createMock(BasekitSubscriptionRepository::class);
        $basekitSubscriptionRepository
            ->expects(self::once())
            ->method('chunkBasekitSubscriptions')
            ->willReturnCallback(static function (callable $callback) use ($subscriptions): void {
                $callback($subscriptions);
            });

        $basekitMigrationService = self::createMock(BasekitMigrationService::class);
        $basekitMigrationService
            ->expects(self::exactly(5))
            ->method('assessEligibility')
            ->willReturnOnConsecutiveCalls(
                BasekitMigrationEligibility::ELIGIBLE,
                BasekitMigrationEligibility::ALREADY_MIGRATED,
                BasekitMigrationEligibility::NOT_SITEBUILDER,
                BasekitMigrationEligibility::NO_PACKAGE_REFERENCE,
                BasekitMigrationEligibility::MISSING_DATA,
            );

        $this->dispatcher->expects(self::never())->method('dispatch');

        $action = new NovaMigrateBasekitDeploymentsAction(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            basekitSubscriptionRepository: $basekitSubscriptionRepository,
            basekitMigrationService: $basekitMigrationService,
        );

        $actionFields = new ActionFields(
            new Collection(['dry-run' => true, 'limit' => 0]),
            new Collection(),
        );

        $actionResponse = $action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();
        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);

        $messageText = (string) $responseData['message'];

        self::assertStringContainsString('Dry run:', $messageText);
        self::assertStringContainsString('1 eligible', $messageText);
        self::assertStringContainsString('skipped already=1', $messageText);
        self::assertStringContainsString('not_sitebuilder=1', $messageText);
        self::assertStringContainsString('no_package_ref=1', $messageText);
        self::assertStringContainsString('missing_data=1', $messageText);
    }

    #[Test]
    public function handleQueuesJobsHonorsLimitAndQueueName(): void
    {
        $subscriptions = new Collection([
            (function () {
                $subscription = new Subscription();
                $subscription->uuid = 'sub-10';

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->uuid = 'sub-11';

                return $subscription;
            })(),
            (function () {
                $subscription = new Subscription();
                $subscription->uuid = 'sub-12';

                return $subscription;
            })(),
        ]);

        $basekitSubscriptionRepository = self::createMock(BasekitSubscriptionRepository::class);
        $basekitSubscriptionRepository
            ->expects(self::once())
            ->method('chunkBasekitSubscriptions')
            ->willReturnCallback(static function (callable $callback) use ($subscriptions): void {
                $callback($subscriptions);
            });

        $basekitMigrationService = self::createMock(BasekitMigrationService::class);
        $basekitMigrationService
            ->expects(self::exactly(2))
            ->method('assessEligibility')
            ->willReturn(
                BasekitMigrationEligibility::ELIGIBLE,
                BasekitMigrationEligibility::ELIGIBLE,
                BasekitMigrationEligibility::ELIGIBLE,
            );

        $this->dispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::callback(static function ($queuedJob): bool {
                if (! $queuedJob instanceof MigrateBasekitSubscriptionJob) {
                    return false;
                }

                return in_array($queuedJob->subscriptionUuid, ['sub-10', 'sub-11'], true);
            }));

        $action = new NovaMigrateBasekitDeploymentsAction(
            dispatcher: $this->dispatcher,
            logger: $this->logger,
            basekitSubscriptionRepository: $basekitSubscriptionRepository,
            basekitMigrationService: $basekitMigrationService,
        );

        $actionFields = new ActionFields(
            new Collection(['dry-run' => false, 'limit' => 2]),
            new Collection(),
        );

        $actionResponse = $action->handle($actionFields);
        $responseData = $actionResponse->jsonSerialize();

        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);

        $messageText = (string) $responseData['message'];
        self::assertSame('Queued 2 migration job(s).', $messageText);
    }
}
