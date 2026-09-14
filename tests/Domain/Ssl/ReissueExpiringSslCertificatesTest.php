<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\SSL\ReissueExpiringSslCertificates;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Models\Provider;

#[CoversClass(ReissueExpiringSslCertificates::class)]
class ReissueExpiringSslCertificatesTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $sslProduct;

    private Provider $rtrProvider;

    private Provider $placeholderProvider;

    private Dispatcher&MockObject $dispatcher;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerFactory::new()->createOne();

        $this->sslProduct = ProductFactory::new()->sslSingleDomain()->createOne();

        $this->rtrProvider = ProviderFactory::new()->sslRtr()->createOne(['default' => true]);
        $this->placeholderProvider = ProviderFactory::new()->sslPlaceholder()->createOne();

        $this->dispatcher = self::createMock(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn (): Dispatcher => $this->dispatcher);
    }

    #[Test]
    public function multipleExpiringSslSubscriptions(): void
    {
        $sslSubscriptionOne = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionTwo = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionThree = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionFour = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionOne->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(3)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionTwo->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(5)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionThree->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(14)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->placeholderProvider->id,
            'subscription_uuid' => $sslSubscriptionFour->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(2)->endOfDay(),
        ]);

        $this->dispatcher->expects(self::exactly(2))->method('dispatch');

        $this->artisan(ReissueExpiringSslCertificates::class)
            ->expectsOutput('2 SSL certificates will expire in 7 days or less')
            ->expectsOutput('SSL certificate reissues queued: 2')
            ->assertOk();
    }

    #[Test]
    public function onlyPlaceholderProvider(): void
    {
        $sslSubscriptionOne = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionTwo = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->placeholderProvider->id,
            'subscription_uuid' => $sslSubscriptionOne->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(3)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->placeholderProvider->id,
            'subscription_uuid' => $sslSubscriptionTwo->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(5)->endOfDay(),
        ]);

        $this->dispatcher->expects(self::never())->method('dispatch');

        $this->artisan(ReissueExpiringSslCertificates::class)
            ->expectsOutput('0 SSL certificates will expire in 7 days or less')
            ->expectsOutput('SSL certificate reissues queued: 0')
            ->assertOk();
    }

    #[Test]
    public function noExpiringSslSubscriptions(): void
    {
        $sslSubscriptionOne = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionTwo = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionOne->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(33)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionTwo->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(55)->endOfDay(),
        ]);

        $this->dispatcher->expects(self::never())->method('dispatch');

        $this->artisan(ReissueExpiringSslCertificates::class)
            ->expectsOutput('0 SSL certificates will expire in 7 days or less')
            ->expectsOutput('SSL certificate reissues queued: 0')
            ->assertOk();
    }

    #[Test]
    public function activeAndCanceledSubscriptions(): void
    {
        $sslSubscriptionOne = SubscriptionFactory::new()->for($this->customer)->for($this->sslProduct)->createOne();

        $sslSubscriptionTwo = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->sslProduct)
            ->administrativeStatusCancelled()
            ->createOne([
                'end_date' => CarbonImmutable::now()->addWeek()->endOfDay(),
            ]);

        $sslSubscriptionThree = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->sslProduct)
            ->administrativeStatusCancelled()
            ->createOne([
                'end_date' => CarbonImmutable::now()->addDays(8)->endOfDay(),
            ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionOne->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(3)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionTwo->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(5)->endOfDay(),
        ]);

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $this->rtrProvider->id,
            'subscription_uuid' => $sslSubscriptionThree->uuid,
            'expire_date' => CarbonImmutable::now()->addDays(5)->endOfDay(),
        ]);

        $this->dispatcher->expects(self::exactly(2))->method('dispatch');

        $this->artisan(ReissueExpiringSslCertificates::class)
            ->expectsOutput('2 SSL certificates will expire in 7 days or less')
            ->expectsOutput('SSL certificate reissues queued: 2')
            ->assertOk();
    }
}
