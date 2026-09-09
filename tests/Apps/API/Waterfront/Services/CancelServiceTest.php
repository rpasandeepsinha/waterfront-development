<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Services;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
class CancelServiceTest extends IntegrationTestCase
{
    private const string DOMAIN = 'versio.nl';

    private Customer $customer;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne();

        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelled::getTemplateSlug(),
        ]);
    }

    #[Test]
    public function cancelDomainServiceAfterEndDate(): void
    {
        $subscription = $this->createDomainSubscription($this->customer);

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel'),
            [
                'subscriptions' => [
                    [
                        'uuid'   => $subscription->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                ],
            ]
        )->assertOk();

        self::assertSame($subscription->id, $response->json('data.0.id'));
        self::assertSame(AdministrativeStatus::CANCELED->value, $response->json('data.0.administrative_status'));
    }

    #[Test]
    public function domainNotCancelledWhenTypeSetAndCancelFalse(): void
    {
        $subscription = $this->createDomainSubscription($this->customer);

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel'),
            [
                'subscriptions' => [
                    [
                        'uuid'   => $subscription->uuid,
                        'cancel' => false,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                ],
            ]
        )->assertOk();

        self::assertSame($subscription->id, $response->json('data.0.id'));
        self::assertSame(AdministrativeStatus::ACTIVE->value, $response->json('data.0.administrative_status'));
    }

    #[Test]
    public function cancelChildSubscriptions(): void
    {
        $parentSubscription = $this->createMicrosoft365Subscription($this->customer);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel.child-subscriptions'),
            [
                'parent' => $parentSubscription->uuid,
                'amount' => 2,
            ]
        )->assertOk();

        $childSubscriptions = Subscription::where('parent_subscription_id', $parentSubscription->id)
            ->where('administrative_status', AdministrativeStatus::CANCELED->value)
            ->get();

        self::assertCount(2, $childSubscriptions);
    }

    #[Test]
    public function cancelAllChildSubscriptions(): void
    {
        $parentSubscription = $this->createMicrosoft365Subscription($this->customer);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel.child-subscriptions'),
            [
                'parent' => $parentSubscription->uuid,
                'amount' => 3,
            ]
        )->assertOk();

        $childSubscriptions = Subscription::where('parent_subscription_id', $parentSubscription->id)
            ->where('administrative_status', AdministrativeStatus::CANCELED->value)
            ->get();

        self::assertCount(3, $childSubscriptions);
        self::assertDatabaseHas('subscriptions', [
            'uuid' => $parentSubscription->uuid,
            'administrative_status' => AdministrativeStatus::CANCELED->value,
        ]);
    }

    #[Test]
    public function cancelChildSubscriptionsFail(): void
    {
        $parentSubscription = $this->createMicrosoft365Subscription($this->customer);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel.child-subscriptions'),
            [
                'parent' => $parentSubscription->uuid,
                'amount' => 5,
            ]
        )
            ->assertBadRequest()
            ->assertJsonFragment([
                'message' => self::resolve(TranslatorInterface::class)->translate('service.cancel.child-subscriptions.fail'),
            ]);
    }

    #[Test]
    public function cancelHostingServiceAfterEndDate(): void
    {
        $subscription = $this->createHostingSubscription($this->customer);

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel'),
            [
                'subscriptions' => [
                    [
                        'uuid'   => $subscription->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                ],
            ]
        )->assertOk();

        self::assertSame($subscription->id, $response->json('data.0.id'));
        self::assertSame(AdministrativeStatus::CANCELED->value, $response->json('data.0.administrative_status'));
    }

    #[Test]
    public function cancelSslService(): void
    {
        $subscription = $this->createSslDeployment($this->customer);

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel'),
            [
                'subscriptions' => [
                    [
                        'uuid'   => $subscription->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                ],
            ]
        )->assertOk();

        self::assertSame($subscription->id, $response->json('data.0.id'));
        self::assertSame(AdministrativeStatus::CANCELED->value, $response->json('data.0.administrative_status'));
    }

    #[Test]
    public function cancelMultipleServices(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
            MailSubscriptionCancelled::class,
            MailSubscriptionCancelled::class,
        ]);

        $domainSubscription = $this->createDomainSubscription($this->customer);
        $hostingSubscription = $this->createHostingSubscription($this->customer);
        $sslDeployment = $this->createSslDeployment($this->customer);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);
        $server = new ServerFactory()->createOne(['type' => ServerType::PLESK]);
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->save();

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel'),
            [
                'subscriptions' => [
                    [
                        'uuid' => $domainSubscription->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                    [
                        'uuid' => $hostingSubscription->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                    [
                        'uuid'   => $sslDeployment->uuid,
                        'cancel' => true,
                        'cancel_type' => SubscriptionCancelType::CANCEL_END_DATE,
                        'cancel_reason' => SubscriptionCancelReason::REASON_CANCELLATION,
                    ],
                ],
            ]
        )->assertOk();

        $idList = [$domainSubscription->id, $hostingSubscription->id, $sslDeployment->id];

        self::assertContains($response->json('data.0.id'), $idList);
        self::assertContains($response->json('data.1.id'), $idList);
        self::assertContains($response->json('data.2.id'), $idList);

        $freshDomain = $domainSubscription->refresh();
        $freshHosting = $hostingSubscription->refresh();
        $freshSsl = $sslDeployment->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $freshDomain->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $freshHosting->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $freshSsl->administrative_status);
    }

    private function createDomainSubscription(Customer $customer): Subscription
    {
        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'extension']);
        $product = new ProductFactory()->createOne(['name' => 'extension', 'product_group_id' => $productGroup->id]);
        $productPrice = new ProductPriceComponentFactory()->registration()->createOne(['product_id' => $product->id]);
        new ProductPriceComponentFactory()->prolongation()->createOne(['product_id' => $product->id]);
        $subscriptionPartnerRepository = self::resolve(SubscriptionRepository::class);

        $subscription = $subscriptionPartnerRepository->create(
            $customer,
            [
                'domain'             => self::DOMAIN,
                'product_name'       => $product->name,
                'product_uuid'       => $product->uuid,
                'gross_price'        => $productPrice->price,
                'net_price'          => $productPrice->price,
                'status'             => DomainStatus::ACTIVE->value,
                'billing_period'     => 12,
                'contract_period'    => 12,
            ]
        );

        DomainDeployment::create([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
        ]);

        return $subscription;
    }

    private function createHostingSubscription(Customer $customer): Subscription
    {
        $server = new ServerFactory()->createOne(['type' => ServerType::PLESK]);
        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'hosting']);
        $product = new ProductFactory()->createOne(['name' => 'basic', 'product_group_id' => $productGroup->id]);
        $productPrice = new ProductPriceComponentFactory()->registration()->createOne(['product_id' => $product->id]);
        new ProductPriceComponentFactory()->prolongation()->createOne(['product_id' => $product->id]);

        $subscriptionPartnerRepository = self::resolve(SubscriptionRepository::class);

        $subscription = $subscriptionPartnerRepository->create(
            $customer,
            [
                'domain'             => self::DOMAIN,
                'product_name'       => $product->name,
                'product_uuid'       => $product->uuid,
                'gross_price'        => $productPrice->price,
                'net_price'          => $productPrice->price,
                'status'             => DomainStatus::ACTIVE->value,
                'contract_period'    => 12,
                'billing_period'     => 12,
            ]
        );

        new HostingDeploymentFactory()->withPleskProvider()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'server_id' => $server->id,
        ]);

        return $subscription;
    }

    private function createSslDeployment(Customer $customer): Subscription
    {
        $sslProvider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'ssl']);
        $product = new ProductFactory()->createOne(['slug' => 'ssl_single_domain', 'product_group_id' => $productGroup->id]);
        $productPrice = new ProductPriceComponentFactory()->registration()->createOne(['product_id' => $product->id]);
        new ProductPriceComponentFactory()->prolongation()->createOne(['product_id' => $product->id]);

        $subscriptionPartnerRepository = self::resolve(SubscriptionRepository::class);

        $subscription = $subscriptionPartnerRepository->create(
            $customer,
            [
                'domain'             => self::DOMAIN,
                'product_name'       => $product->name,
                'product_uuid'       => $product->uuid,
                'gross_price'        => $productPrice->price,
                'net_price'          => $productPrice->price,
                'status'             => DomainStatus::ACTIVE->value,
                'billing_period'     => 12,
                'contract_period'    => 12,
            ]
        );

        SslDeployment::create([
            'subscription_uuid' => $subscription->uuid,
            'certificate_id' => 123,
            'request_id'     => 123,
            'provider_id' => $sslProvider->id,
        ]);

        return $subscription;
    }

    private function createMicrosoft365Subscription(Customer $customer): Subscription
    {
        $productGroup = new ProductGroupFactory()->createOne(['slug' => 'microsoft-365']);
        $parentProduct = new ProductFactory()->createOne(['name' => 'microsoft-business-basic-parent', 'product_group_id' => $productGroup->id]);
        $product = new ProductFactory()->createOne(['name' => 'microsoft-business-basic', 'product_group_id' => $productGroup->id]);
        new ProductPriceComponentFactory()->prolongation()->createOne(['product_id' => $product->id]);

        $parentSubscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $parentProduct->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'customer_id' => $customer->id,
        ]);

        new SubscriptionFactory()->withCustomer()->createOne([
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $product->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'customer_id' => $customer->id,
        ]);

        new SubscriptionFactory()->withCustomer()->createOne([
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $product->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'customer_id' => $customer->id,
        ]);

        new SubscriptionFactory()->withCustomer()->createOne([
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $product->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'customer_id' => $customer->id,
        ]);

        return $parentSubscription;
    }
}
