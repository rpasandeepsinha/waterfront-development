<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Jobs\TechnicalSslMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(TechnicalSslMigrationJob::class)]
class TechnicalSslMigrationJobTest extends IntegrationTestCase
{
    private Subscription $sslSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $customer = CustomerFactory::new()->createOne();

        $sslGroup = ProductGroupFactory::new()->ssl()->createOne();
        $sslProduct = ProductFactory::new()->for($sslGroup)->createOne();

        $this->sslSubscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($sslProduct)
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_subscription_id' => 'sub_1337_1',
        ]);
        $this->sslSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $migratedCustomer->customers()->attach($customer);
        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);

        $this->sslSubscription->save();

        $sslPlaceholderProvider = ProviderFactory::new()->sslPlaceholder()->createOne();

        SslDeploymentFactory::new()->for($this->sslSubscription, 'subscription')->for(
            $sslPlaceholderProvider,
            'provider',
        )->createOne();
    }

    #[Test]
    public function rollback(): void
    {
        $job = new TechnicalSslMigrationJob(
            subscription: $this->sslSubscription,
            failedTechnicalStatus: TechnicalStatus::ERROR->value,
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        self::expectException(ModelNotFoundException::class);

        $job->handle($adfService, $dispatcher, $logger);

        $this->sslSubscription->refresh();

        self::assertSame(
            ProviderSlug::PLACEHOLDER,
            $this->sslSubscription->sslDeployment?->provider->slug,
        );
        self::assertNull($this->sslSubscription->sslDeployment->expire_date);
    }
}
