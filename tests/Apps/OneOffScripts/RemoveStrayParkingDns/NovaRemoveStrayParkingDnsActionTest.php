<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\RemoveStrayParkingDns;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Responses\Message as NovaMessage;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\OneOffScript;
use Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns\NovaRemoveStrayParkingDnsAction;
use Waterfront\Apps\OneOffScripts\RemoveStrayParkingDns\RemoveStrayParkingDnsJob;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(NovaRemoveStrayParkingDnsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaRemoveStrayParkingDnsActionTest extends IntegrationTestCase
{
    private Dispatcher&MockObject $jobDispatcher;

    private SubscriptionRepository&MockObject $subscriptionRepository;

    private LoggerInterface&Stub $logger;

    /**
     * @var list<RemoveStrayParkingDnsJob>
     */
    private array $dispatchedJobs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobDispatcher = self::createMock(Dispatcher::class);
        $this->subscriptionRepository = self::createMock(SubscriptionRepository::class);
        $this->logger = self::createStub(LoggerInterface::class);

        $this->dispatchedJobs = [];
        $this->jobDispatcher
            ->method('dispatch')
            ->willReturnCallback(function (RemoveStrayParkingDnsJob $job): null {
                $this->dispatchedJobs[] = $job;

                return null;
            });
    }

    public function testDryRunDispatchesJobPerDomainWithoutRegisteringExecution(): void
    {
        $this->subscriptionRepository->method('getRecentDomainNames')->willReturn(['first.nl', 'second.nl']);

        $actionResponse = $this->buildAction()->handle($this->fields(dryRun: true));

        self::assertEquals(
            [
                new RemoveStrayParkingDnsJob('first.nl', true),
                new RemoveStrayParkingDnsJob('second.nl', true),
            ],
            $this->dispatchedJobs,
        );

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertStringContainsString('Dry run', (string) $responseData['message']);

        self::assertNull($this->oneOffScript()->last_executed_at);
    }

    public function testRealRunDispatchesJobPerDomain(): void
    {
        $this->subscriptionRepository->method('getRecentDomainNames')->willReturn(['first.nl', 'second.nl']);

        $actionResponse = $this->buildAction()->handle($this->fields(dryRun: false));

        self::assertEquals(
            [
                new RemoveStrayParkingDnsJob('first.nl', false),
                new RemoveStrayParkingDnsJob('second.nl', false),
            ],
            $this->dispatchedJobs,
        );

        $responseData = $actionResponse->jsonSerialize();
        self::assertInstanceOf(NovaMessage::class, $responseData['message']);
        self::assertStringContainsString('Dispatched 2 domains', (string) $responseData['message']);
    }

    public function testDispatchesNothingWhenNoRecentDomains(): void
    {
        $this->subscriptionRepository->method('getRecentDomainNames')->willReturn([]);

        $this->buildAction()->handle($this->fields(dryRun: true));

        self::assertSame([], $this->dispatchedJobs);
    }

    private function buildAction(): NovaRemoveStrayParkingDnsAction
    {
        return new NovaRemoveStrayParkingDnsAction(
            jobDispatcher: $this->jobDispatcher,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
        );
    }

    private function fields(bool $dryRun): ActionFields
    {
        return new ActionFields(
            new Collection(['dry-run' => $dryRun, 'amount' => 500]),
            new Collection(),
        );
    }

    private function oneOffScript(): OneOffScript
    {
        /** @var OneOffScript $oneOffScript */
        $oneOffScript = OneOffScript::query()->where('slug', NovaRemoveStrayParkingDnsAction::SLUG)->firstOrFail();

        return $oneOffScript;
    }
}
