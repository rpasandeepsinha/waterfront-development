<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Jobs\TerminateMailOnlyHosting;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(TerminateMailOnlyHosting::class)]
class TerminateMailOnlyHostingTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private Dispatcher $dispatcher;

    private TerminateMailOnlyHosting $terminateMailOnlyHostingJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->forDomain('test.com')
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()))
            ->technicalStatusOk()
            ->createOne();

        self::assertNotNull($this->subscription->domain);

        $createMailOnlyHosting = new CreateMailOnlyHosting(
            contactPersonName: $this->subscription->customer->name,
            contactEmail: $this->subscription->customer->email,
            subscription: $this->subscription
        );

        $this->dispatcher = self::resolve(Dispatcher::class);

        $this->terminateMailOnlyHostingJob = new TerminateMailOnlyHosting(
            $createMailOnlyHosting
        );
    }

    #[Test]
    public function catchesException(): void
    {
        $mockLogger = self::createMock(LoggerInterface::class);
        $mailOnlyService = self::createMock(MailManagementService::class);

        $mockLogger->expects(self::once())
            ->method('error')
            ->with('Error while terminating the mailOnly subscription');

        $mailOnlyService->expects(self::once())
            ->method('terminate')
            ->willThrowException(new MailOnlyException());

        $this->terminateMailOnlyHostingJob->handle($mailOnlyService, $mockLogger);
    }

    #[Test]
    public function failedJobSetsTechnicalStatusToDeletingFailed(): void
    {
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);

        $this->terminateMailOnlyHostingJob->failed(new MailOnlyException());

        self::assertSame(TechnicalStatus::DELETING_FAILED->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function updateNameServersDispatchAsync(): void
    {
        Bus::fake();

        $this->dispatcher->dispatch($this->terminateMailOnlyHostingJob);

        Bus::assertNotDispatchedSync(TerminateMailOnlyHosting::class);
    }
}
