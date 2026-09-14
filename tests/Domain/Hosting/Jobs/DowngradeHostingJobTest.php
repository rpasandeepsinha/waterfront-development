<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Jobs;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Jobs\DowngradeHostingJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDowngradeExecutor;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

#[CoversClass(DowngradeHostingJob::class)]
class DowngradeHostingJobTest extends IntegrationTestCase
{
    private Subscription $subscription;

    private SubscriptionMutation $mutation;

    private SubscriptionChange $subscriptionChange;

    private HostingDowngradeExecutor&MockObject $hostingDowngradeExecutor;

    public function setUp(): void
    {
        parent::setUp();

        $this->hostingDowngradeExecutor = self::createMock(HostingDowngradeExecutor::class);

        $customer = new CustomerFactory()->createOne();

        $group = new ProductGroupFactory()->hosting()->createOne();
        $grootProduct = new ProductFactory()->for($group)->createOne();
        $smallProduct = new ProductFactory()->for($group)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($customer)
            ->for($grootProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::OK,
            ]);

        $this->mutation = new SubscriptionMutationFactory()->for($this->subscription)->createOne([
            'product_id' => $smallProduct->id,
            'process_technical_at' => CarbonImmutable::now(),
            'processed_technical_at' => null,
        ]);

        $this->subscriptionChange = new SubscriptionChangeFactory()->for($this->subscription)->createOne([
            'from_product_uuid' => $grootProduct->uuid,
            'to_product_uuid' => $smallProduct->uuid,
            'status' => SubscriptionChangeStatus::REQUESTED,
            'type' => ProductChangeType::DOWNGRADE,
            'requested_at' => CarbonImmutable::now(),
            'completed_at' => null,
        ]);
        $hostingProvider = new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::DIRECTADMIN,
            'enabled' => true,
            'default' => true,
        ]);

        new HostingDeploymentFactory()->for($this->subscription, 'subscription')->createOne([
            'provider_id' => $hostingProvider->id,
        ]);
    }

    #[Test]
    public function downgradeSuccesful(): void
    {
        $this->hostingDowngradeExecutor
            ->expects(self::once())
            ->method('execute')
            ->with(
                self::identicalTo($this->subscription),
                self::isInstanceOf(HostingDeployment::class),
                self::identicalTo($this->subscription->product),
                self::identicalTo($this->mutation->product),
            )
            ->willReturn(new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_OK));

        $job = new DowngradeHostingJob($this->subscription, $this->mutation, $this->subscriptionChange);
        $job->handle(
            self::createStub(LoggerInterface::class),
            $this->hostingDowngradeExecutor,
        );

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::COMPLETED, $this->subscriptionChange->status);
        self::assertNotNull($this->mutation->processed_technical_at);
    }

    #[Test]
    public function downgradeFailsWithExecutorMessage(): void
    {
        $this->hostingDowngradeExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturn(new SubscriptionChangeResult(
                status: SubscriptionChangeResult::STATUS_ERROR,
                errorMessage: "Current subscription package doesn't meet requirements to be downgraded",
            ));

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects(self::never())->method('send');

        $job = new DowngradeHostingJob($this->subscription, $this->mutation, $this->subscriptionChange);
        $job->handle(
            self::createStub(LoggerInterface::class),
            $this->hostingDowngradeExecutor,
        );

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::EXECUTION_FAILED, $this->subscriptionChange->status);
        self::assertStringContainsString(
            "Current subscription package doesn't meet requirements to be downgraded",
            $this->subscription->hostingDeployment->last_created_result ?? '',
        );
        self::assertSame(
            "Current subscription package doesn't meet requirements to be downgraded",
            $this->subscriptionChange->failure_message,
        );
        self::assertSame(0, $this->subscriptionChange->failure_code);
        self::assertNotNull($this->mutation->processed_technical_at);
    }

    #[Test]
    public function downgradeFailsWithFallbackMessageWhenExecutorMessageEmpty(): void
    {
        $this->hostingDowngradeExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturn(new SubscriptionChangeResult(status: SubscriptionChangeResult::STATUS_ERROR));

        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects(self::never())->method('send');

        $unsuspendJob = new DowngradeHostingJob($this->subscription, $this->mutation, $this->subscriptionChange);
        $unsuspendJob->handle(
            self::createStub(LoggerInterface::class),
            $this->hostingDowngradeExecutor,
        );

        $this->subscription->refresh();
        $this->subscriptionChange->refresh();

        self::assertNotNull($this->subscription->hostingDeployment);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->technical_status);
        self::assertSame(SubscriptionChangeStatus::EXECUTION_FAILED, $this->subscriptionChange->status);
        self::assertStringContainsString(
            'Hosting change failed, original error message empty.',
            $this->subscription->hostingDeployment->last_created_result ?? '',
        );
        self::assertStringContainsString(
            'Hosting change failed, original error message empty.',
            $this->subscriptionChange->failure_message ?? '',
        );
        self::assertNotNull($this->mutation->processed_technical_at);
    }
}
