<?php

declare(strict_types=1);

namespace Tests\Domain\Placeholder\Integration\Services;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\Placeholder\Services\SslPlaceholderService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(SslPlaceholderService::class)]
class SslPlaceholderServiceTest extends IntegrationTestCase
{
    private Product $product;

    private ProductSpec $productSpec;

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::SSL,
        ]);

        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Extended Validation',
            'slug' => 'ssl_extended_validation',
        ]);

        $this->productSpec = new ProductSpecFactory()->for($this->product)->createOne([
            'name' => 'ssl.product_id',
            'value' => 31,
        ]);

        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
    }

    #[Test]
    public function create(): void
    {
        self::assertEmailsSend([
            OrderedManualSubscriptionEmployee::class,
        ]);

        $placeholderService = self::resolve(SslPlaceholderService::class);

        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => false]);

        $subscription = new SubscriptionFactory()->withCustomer()->for($this->product)->createOne();
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        new TemplateFactory()->createOne([
            'slug' => OrderedManualSubscriptionEmployee::getTemplateSlug(),
        ]);

        $result = $placeholderService->create(
            $this->productSpec,
            12,
            $this->customer->toArray(),
            $sslDeployment
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result->getStatus());
        self::assertDatabaseHas('ssl_deployments', [
           'subscription_uuid' => $subscription->uuid,
           'provider_id' => $provider->id,
       ]);
    }

    #[Test]
    public function observerTechnicalStatusSubscription(): void
    {
        $mockMail = self::createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $customer = new CustomerFactory()->createOne();
        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($customer)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
        $subscription->sslDeployment()->save($sslDeployment);
        $subscription->save();

        $mockMail->expects(self::once())
            ->method('send')
            ->with(
                self::callback(function (array $recipients) use ($customer) {
                    self::assertSame($customer->getEmail(), $recipients[0]->getEmail());
                    self::assertCount(1, $recipients);
                    return true;
                }),
                self::callback(function (MailTemplateInterface $template) {
                    self::assertInstanceOf(ActivatedManualSubscriptionCustomer::class, $template);
                    return true;
                }),
            );

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }

    #[Test]
    public function observerTechnicalStatusSubscriptionWrongStatus(): void
    {
        $mockMail = self::createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->technicalStatusOk()
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $subscription->sslDeployment()->save($sslDeployment);
        $subscription->save();

        $mockMail->expects(self::never())->method('send');

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }
}
