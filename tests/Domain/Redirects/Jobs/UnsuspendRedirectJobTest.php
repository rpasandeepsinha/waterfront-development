<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Jobs;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Jobs\UnsuspendRedirectJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(UnsuspendRedirectJob::class)]
class UnsuspendRedirectJobTest extends IntegrationTestCase
{
    private Subscription $redirectSubscription;

    public function setUp(): void
    {
        parent::setUp();

        $this->redirectSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->redirect()->createOne())
            ->createOne(
                [
                    'technical_status' => TechnicalStatus::OK->value,
                    'suspended_at' => CarbonImmutable::now(),
                ]
            );
    }

    #[Test]
    public function suspendRedirectJobSuccessful(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionUnSuspendedMailAction = self::createMock(SendSubscriptionUnSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new RedirectResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::SUCCESS,
                )
            );

        $sendSubscriptionUnSuspendedMailAction->expects(self::once())
            ->method('execute')
            ->with($this->redirectSubscription);

        $suspendRedirectjob = new UnsuspendRedirectJob($this->redirectSubscription);
        $suspendRedirectjob->handle(
            logger: $logger,
            sendSubscriptionUnSuspendedMailAction: $sendSubscriptionUnSuspendedMailAction,
            provisionGateway: $provisionGateway,
        );

        $this->redirectSubscription->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->redirectSubscription->technical_status);
        self::assertNull($this->redirectSubscription->suspended_at);
    }

    #[Test]
    public function suspendRedirectJobFails(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionUnSuspendedMailAction = self::createMock(SendSubscriptionUnSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new RedirectResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::FAILED,
                )
            );

        $sendSubscriptionUnSuspendedMailAction->expects(self::never())
            ->method('execute');

        $suspendRedirectJob = new UnsuspendRedirectJob($this->redirectSubscription);
        $suspendRedirectJob->handle(
            logger: $logger,
            sendSubscriptionUnSuspendedMailAction: $sendSubscriptionUnSuspendedMailAction,
            provisionGateway: $provisionGateway,
        );

        $this->redirectSubscription->refresh();
        self::assertSame(TechnicalStatus::UNSUSPENSION_FAILED->value, $this->redirectSubscription->technical_status);
        self::assertNotNull($this->redirectSubscription->suspended_at);
    }
}
