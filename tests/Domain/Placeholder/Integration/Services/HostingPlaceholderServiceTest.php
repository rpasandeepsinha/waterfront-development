<?php

declare(strict_types=1);

namespace Tests\Domain\Placeholder\Integration\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledManualSubscriptionEmployee;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\Placeholder\Services\HostingPlaceholderService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(HostingPlaceholderService::class)]
class HostingPlaceholderServiceTest extends IntegrationTestCase
{
    private Product $product;

    private HostingPlaceholderService $placeholderService;

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Basic test hosting',
            'slug' => 'basic_hosting',
        ]);

        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);

        $this->placeholderService = self::resolve(HostingPlaceholderService::class);
    }

    #[Test]
    public function create(): void
    {
        self::assertEmailsSend([
            OrderedManualSubscriptionEmployee::class,
        ]);

        $this->placeholderService = self::resolve(HostingPlaceholderService::class);

        $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);

        $subscription = new SubscriptionFactory()->withCustomer()->for($this->product)->createOne();

        $result = $this->placeholderService->create(
            contactPersonName: $this->customer->contact_name,
            contactEmail: $this->customer->email,
            customerEmail: $this->customer->email,
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result['result']);
        self::assertNull($result['domain']);
        self::assertEmpty($result['username']);

        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);
    }

    #[Test]
    public function createWithExistingHostingSubscription(): void
    {
        self::assertEmailsSend([
            OrderedManualSubscriptionEmployee::class,
        ]);

        $this->placeholderService = self::resolve(HostingPlaceholderService::class);

        $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);

        $subscription = new SubscriptionFactory()->withCustomer()->for($this->product)->createOne();
        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $result = $this->placeholderService->create(
            contactPersonName: $this->customer->contact_name,
            contactEmail: $this->customer->email,
            customerEmail: $this->customer->email,
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result['result']);
        self::assertNull($result['domain']);
        self::assertEmpty($result['username']);

        self::assertDatabaseHas('hosting_deployments', [
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);
    }

    #[Test]
    public function createFailedMissingProvider(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $subscription = new SubscriptionFactory()->withCustomer()->for($this->product)->createOne();

        $this->placeholderService->create(
            contactPersonName: $this->customer->contact_name,
            contactEmail: $this->customer->email,
            customerEmail: $this->customer->email,
            customerUuid: $this->customer->uuid,
            subscriptionUuid: $subscription->uuid,
            specs: [],
        );
    }

    #[Test]
    public function terminate(): void
    {
        self::assertEmailsSend([
            CanceledManualSubscriptionEmployee::class,
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->for($this->product)->createOne([
            'domain' => 'testdomain.nl',
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $domain = $subscription->domain;
        self::assertNotNull($domain);

        $result = self::resolve(HostingPlaceholderService::class)->terminate($domain, $subscription->uuid);

        self::assertTrue($result);
    }

    #[Test]
    public function observerTechnicalStatusSubscription(): void
    {
        $mockMail = self::createMock(MailerInterface::class);
        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $provider = new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => true]);
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
        $subscription->hostingDeployment()->save($hostingDeployment);
        $subscription->save();

        $mockMail->expects(self::once())
            ->method('send')
            ->with(
                self::callback(function (array $recipients) use ($subscription) {
                    self::assertSame($subscription->customer->getEmail(), $recipients[0]->getEmail());
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
        $mockMail->expects(self::never())->method('send');

        $this->app->bind(MailerInterface::class, fn (): MailerInterface => $mockMail);

        $subscriptionUuid = Str::uuid();
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->product)
            ->technicalStatusOk()
            ->createOne([
                'uuid' => $subscriptionUuid,
                'domain' => null,
            ]);

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }
}
