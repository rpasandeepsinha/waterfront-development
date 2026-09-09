<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase;

use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\CreateRedirectsFromLegacyDatabaseJob;
use Waterfront\Apps\OneOffScripts\CreateRedirectsFromLegacyDatabase\NovaCreateRedirectsFromLegacyDatabaseAction;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(NovaCreateRedirectsFromLegacyDatabaseAction::class)]
class NovaCreateRedirectsFromLegacyDatabaseActionTest extends IntegrationTestCase
{
    private Dispatcher&MockInterface $dispatcher;

    private Factory&MockInterface $redisFactory;

    private LoggerInterface&MockInterface $mockLogger;

    private NovaCreateRedirectsFromLegacyDatabaseAction $action;

    public function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::mock(Dispatcher::class);
        $this->redisFactory = self::mock(Factory::class);
        $this->mockLogger = self::mock(LoggerInterface::class);

        $this->action = new NovaCreateRedirectsFromLegacyDatabaseAction(
            logger: $this->mockLogger,
            busDispatcher: $this->dispatcher,
            redisFactory: $this->redisFactory,
        );
    }

    #[Test]
    public function handle(): void
    {
        $amountOfRedirects = 5;
        $limit = 3;
        $alreadyProcessedUuid = Uuid::uuid4()->toString();
        $redirect = ProductFactory::new()->redirect()->createOne();

        // Redirect Subscriptions
        SubscriptionFactory::new()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->for($redirect)
            ->count($amountOfRedirects)
            ->createMany();

        $alreadyProcessed = SubscriptionFactory::new()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->for($redirect)
            ->createOne([
                'uuid' => $alreadyProcessedUuid,
            ]);

        $nonActive = SubscriptionFactory::new()
            ->withCustomer()
            ->administrativeStatusArchived()
            ->for($redirect)
            ->count($amountOfRedirects)
            ->createOne();

        $nonRedirect = SubscriptionFactory::new()
            ->withCustomer()
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->for(ProductFactory::new()->hostingGold())
            ->createOne();

        $actionFields = new ActionFields(
            new Collection(['dry-run' => false, 'only-existing' => false, 'limit' => $limit]),
            new Collection(),
        );

        $this->mockLogger->expects('debug')
            ->with(
                sprintf('Executing one-time script %s', NovaCreateRedirectsFromLegacyDatabaseAction::SLUG),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaCreateRedirectsFromLegacyDatabaseAction::SLUG,
                    LoggingContextKeys::META => [
                        'dry-run' => false,
                        'only-existing' => false,
                        'limit' => $limit,
                    ],
                ]
            );

        $this->redisFactory->expects('connection->sMembers')
            ->with(NovaCreateRedirectsFromLegacyDatabaseAction::REDIS_PROCESSED_KEY)
            ->andReturn([$alreadyProcessedUuid]);

        $this->dispatcher->expects('dispatch')
            ->times($limit)
            ->withArgs(
                fn (CreateRedirectsFromLegacyDatabaseJob $job) => ! in_array($job->subscription->uuid, [$alreadyProcessed->uuid, $nonActive->uuid, $nonRedirect->uuid], true)
            );

        $response = $this->action->handle($actionFields);
        $responseArray = $response->jsonSerialize();

        self::assertArrayHasKey('message', $responseArray);
        self::assertInstanceOf(Message::class, $responseArray['message']);
        self::assertSame(sprintf('One-off script dispatched %d jobs to queue.', $limit), $responseArray['message']->text);
    }
}
