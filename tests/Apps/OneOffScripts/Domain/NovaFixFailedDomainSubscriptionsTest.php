<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Domain;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\DiagnoseFailedDomainSubscriptionJob;
use Waterfront\Apps\OneOffScripts\Domain\NovaFixFailedDomainSubscriptionsAction;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(NovaFixFailedDomainSubscriptionsAction::class)]
class NovaFixFailedDomainSubscriptionsTest extends IntegrationTestCase
{
    private MockObject&Dispatcher $dispatcher;

    private Stub&SubscriptionRepository $subscriptionRepository;

    private NovaFixFailedDomainSubscriptionsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::createMock(Dispatcher::class);
        $this->subscriptionRepository = self::createStub(SubscriptionRepository::class);

        $this->action = new NovaFixFailedDomainSubscriptionsAction(
            dispatcher: $this->dispatcher,
            subscriptionRepository: $this->subscriptionRepository,
        );
    }

    #[Test]
    public function handleQueuesDiagnoseJobForEachFailedSubscriptionInDryRun(): void
    {
        $subscriptionOne = SubscriptionFactory::new()->forDomain('example-one.test')->makeOne();

        $domainDeploymentOne = new DomainDeployment();
        $domainDeploymentOne->id = 501;
        $subscriptionOne->setRelation('domainDeployment', $domainDeploymentOne);

        $subscriptionTwo = SubscriptionFactory::new()->forDomain('example-two.test')->makeOne();

        $domainDeploymentTwo = new DomainDeployment();
        $domainDeploymentTwo->id = 502;
        $subscriptionTwo->setRelation('domainDeployment', $domainDeploymentTwo);

        $this->subscriptionRepository
            ->method('getFailedDomainSubscriptionsWithDeployment')
            ->willReturn(new EloquentCollection([$subscriptionOne, $subscriptionTwo]));

        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        $actionFields = new ActionFields(
            new Collection(['dry-run' => true]),
            new Collection(),
        );

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();

        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            'Queued 2 failed domain subscriptions for async processing. Dry run: yes.',
            (string) $responseData['message'],
        );

        self::assertCount(2, $queuedJobs);

        self::assertEquals(
            new DiagnoseFailedDomainSubscriptionJob(
                subscription: $subscriptionOne,
                dryRun: true,
                triggeredBy: NovaFixFailedDomainSubscriptionsAction::SLUG,
            ),
            $queuedJobs[0],
        );

        self::assertEquals(
            new DiagnoseFailedDomainSubscriptionJob(
                subscription: $subscriptionTwo,
                dryRun: true,
                triggeredBy: NovaFixFailedDomainSubscriptionsAction::SLUG,
            ),
            $queuedJobs[1],
        );
    }

    #[Test]
    public function handleQueuesDiagnoseJobWithDryRunDisabled(): void
    {
        $subscription = SubscriptionFactory::new()->forDomain('example-three.test')->makeOne();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->id = 601;
        $subscription->setRelation('domainDeployment', $domainDeployment);

        $this->subscriptionRepository
            ->method('getFailedDomainSubscriptionsWithDeployment')
            ->willReturn(new EloquentCollection([$subscription]));

        $queuedJobs = [];

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $job) use (&$queuedJobs): void {
                $queuedJobs[] = $job;
            });

        $actionFields = new ActionFields(
            new Collection(['dry-run' => false]),
            new Collection(),
        );

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();

        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            'Queued 1 failed domain subscriptions for async processing. Dry run: no.',
            (string) $responseData['message'],
        );

        self::assertCount(1, $queuedJobs);

        self::assertEquals(
            new DiagnoseFailedDomainSubscriptionJob(
                subscription: $subscription,
                dryRun: false,
                triggeredBy: NovaFixFailedDomainSubscriptionsAction::SLUG,
            ),
            $queuedJobs[0],
        );
    }

    #[Test]
    public function handleWithNoFailedSubscriptionsQueuesNoJobs(): void
    {
        $this->subscriptionRepository
            ->method('getFailedDomainSubscriptionsWithDeployment')
            ->willReturn(new EloquentCollection());

        $this->dispatcher->expects(self::never())->method('dispatch');

        $actionFields = new ActionFields(
            new Collection(['dry-run' => true]),
            new Collection(),
        );

        $actionResponse = $this->action->handle($actionFields);

        $responseData = $actionResponse->jsonSerialize();

        self::assertArrayHasKey('message', $responseData);
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertSame(
            'Queued 0 failed domain subscriptions for async processing. Dry run: yes.',
            (string) $responseData['message'],
        );
    }
}
