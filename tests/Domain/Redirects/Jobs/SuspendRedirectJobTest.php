<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Jobs\SuspendRedirectJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SuspendRedirectJob::class)]
class SuspendRedirectJobTest extends IntegrationTestCase
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
                    'suspended_at' => null,
                ]
            );
    }

    #[Test]
    public function suspendRedirectJobSuccessful(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionSuspendedMailAction = self::createMock(SendSubscriptionSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new RedirectResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::SUCCESS,
                )
            );

        $sendSubscriptionSuspendedMailAction->expects(self::once())
            ->method('execute')
            ->with($this->redirectSubscription);

        $suspendRedirectJob = new SuspendRedirectJob($this->redirectSubscription);
        $suspendRedirectJob->handle(
            logger: $logger,
            sendSubscriptionSuspendedMailAction: $sendSubscriptionSuspendedMailAction,
            provisionGateway: $provisionGateway,
        );

        $this->redirectSubscription->refresh();
        self::assertSame(TechnicalStatus::SUSPENDED->value, $this->redirectSubscription->technical_status);
        self::assertNotNull($this->redirectSubscription->suspended_at);
    }

    #[Test]
    public function suspendRedirectJobFails(): void
    {
        $logger = self::createStub(LoggerInterface::class);
        $sendSubscriptionSuspendedMailAction = self::createMock(SendSubscriptionSuspendedMailAction::class);
        $provisionGateway = self::createMock(ProvisionGateway::class);

        $provisionGateway->expects(self::once())
            ->method('request')
            ->willReturn(
                new RedirectResult(
                    provisionData: self::createStub(ProvisionRequestInterface::class),
                    provisionStatus: ProvisionStatus::FAILED,
                )
            );

        $sendSubscriptionSuspendedMailAction->expects(self::never())
            ->method('execute');

        $suspendRedirectJob = new SuspendRedirectJob($this->redirectSubscription);
        $suspendRedirectJob->handle(
            logger: $logger,
            sendSubscriptionSuspendedMailAction: $sendSubscriptionSuspendedMailAction,
            provisionGateway: $provisionGateway,
        );

        $this->redirectSubscription->refresh();
        self::assertSame(TechnicalStatus::SUSPENSION_FAILED->value, $this->redirectSubscription->technical_status);
        self::assertNull($this->redirectSubscription->suspended_at);
    }
}
