<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Exceptions\CancelNotRevertedException;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelReverted;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(CancellationService::class)]
class SubscriptionRevertCancelTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private Product $product;

    private string $domainName = 'example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($this->customer);

        $group = new ProductGroupFactory()->extension()->createOne();
        $this->product = new ProductFactory()->for($group)->createOne(
            ['name' => '.nl', 'slug' => 'extension_nl']
        );
        $this->subscription = new SubscriptionFactory()->for($this->product)->createOne(
            [
                'customer_id'  => $this->customer->id,
                'domain'       => $this->domainName,
                'start_date'   => CarbonImmutable::now(),
                'end_date'     => CarbonImmutable::now()->addWeek(),
                'gross_price'  => 100,
                'net_price'    => 100,
            ]
        );

        new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $this->subscription->uuid,
        ]);
    }

    /**
     * @throws CancelNotRevertedException
     */
    #[Test]
    public function subscriptionCancelAndRevert(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
            MailSubscriptionCancelReverted::class,
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelReverted::getTemplateSlug(),
        ]);
        new TemplateFactory()->createOne([
            'slug' => MailSubscriptionCancelled::getTemplateSlug(),
        ]);

        self::assertDatabaseHas(
            'subscriptions',
            [
                'id'                     => $this->subscription->id,
                'uuid'                   => $this->subscription->uuid,
                'product_uuid'           => $this->product->uuid,
                'customer_id'            => $this->customer->id,
                'technical_status'       => null,
                'administrative_status'  => AdministrativeStatus::ACTIVE->value,
                'contract_period'        => '12',
                'billing_period'         => '12',
                'net_price'              => 100,
                'gross_price'            => 100,
                'parent_subscription_id' => null,
                'cancel_date'            => null,
            ]
        );

        $service = self::resolve(CancellationService::class);

        $service->cancel($this->subscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscription->administrative_status);
        self::assertNotNull($this->subscription->cancel_date);
        self::assertNotNull($this->subscription->cancel_reason);

        $service->revertCancel($this->subscription);

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertNull($this->subscription->cancel_date);
        self::assertNull($this->subscription->cancel_reason);
    }

    #[Test]
    public function subscriptionCancelAndRevertFailedNoCancelledStatus(): void
    {
        $service = self::resolve(CancellationService::class);

        $this->expectException(CancelNotRevertedException::class);
        $service->revertCancel($this->subscription);
    }

    /**
     * @throws CancelNotRevertedException
     */
    #[Test]
    public function freeRedirectAlsoGetsReverted(): void
    {
        $freeRedirectSubscription = $this->createFreeRedirectSubscription();
        $freeRedirectSubscription->administrative_status = AdministrativeStatus::CANCELED->value;

        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = CarbonImmutable::now();

        $service = self::resolve(CancellationService::class);
        $service->revertCancel($this->subscription);

        $freeRedirectSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $freeRedirectSubscription->administrative_status);
    }

    /**
     * @throws CancelNotRevertedException
     */
    #[Test]
    public function deletedFreeRedirectDoesNotGetReverted(): void
    {
        $freeRedirectSubscription = $this->createFreeRedirectSubscription();
        $freeRedirectSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;

        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = CarbonImmutable::now();

        $service = self::resolve(CancellationService::class);
        $service->revertCancel($this->subscription);

        $freeRedirectSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $freeRedirectSubscription->administrative_status);
    }

    /**
     * @throws CancelNotRevertedException
     */
    #[Test]
    public function dnsAlsoGetsReverted(): void
    {
        $dnsSubscription = $this->createDnsSubscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::CANCELED->value;

        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = CarbonImmutable::now();

        $service = self::resolve(CancellationService::class);
        $service->revertCancel($this->subscription);

        $dnsSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);
    }

    /**
     * @throws CancelNotRevertedException
     */
    #[Test]
    public function deletedDnsDoesNotGetReverted(): void
    {
        $dnsSubscription = $this->createDnsSubscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ARCHIVED->value;

        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = CarbonImmutable::now();

        $service = self::resolve(CancellationService::class);
        $service->revertCancel($this->subscription);

        $dnsSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);
    }

    #[DataProvider('subscriptionDataProvider')]
    #[Test]
    public function subscriptionRevertCancel(
        CarbonImmutable $start_date,
        CarbonImmutable $end_date,
        CarbonImmutable $cancel_date,
    ): void {
        self::assertEmailsSend([
            MailSubscriptionCancelReverted::class,
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne(
            [
                'product_uuid'          => $this->product->uuid,
                'customer_id'           => $this->customer->id,
                'start_date'            => $start_date,
                'end_date'              => $end_date,
                'cancel_date'           => $cancel_date,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]
        );

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel.revert', $subscription->uuid)
        )->assertOk();

        $subscription->refresh();

        $response->assertJsonPath('message', 'services.revert-cancel-subscription.successful');

        self::assertSame(AdministrativeStatus::ACTIVE->value, $subscription->administrative_status);
    }

    /**
     * @return array<array<CarbonImmutable>>
     */
    public static function subscriptionDataProvider(): array
    {
        return [
            [
                CarbonImmutable::today()->subMonth(),
                CarbonImmutable::today()->addWeek(),
                CarbonImmutable::tomorrow(),
            ],
            [
                CarbonImmutable::today()->subMonth(),
                CarbonImmutable::today(),
                CarbonImmutable::today(),
            ],
            [
                CarbonImmutable::today(),
                CarbonImmutable::today()->addMonth(),
                CarbonImmutable::today(),
            ],
        ];
    }

    #[Test]
    public function subscriptionRevertCancelExpired(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())
            ->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne(
            [
                'product_uuid'          => $this->product->uuid,
                'customer_id'           => $this->customer->id,
                'start_date'            => CarbonImmutable::today(),
                'end_date'              => CarbonImmutable::today()->subWeek(),
                'cancel_date'           => CarbonImmutable::today(),
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]
        );
        $subscription->refresh();

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.subscriptions.cancel.revert', $subscription->uuid)
        );

        $response->assertJsonPath('message', self::resolve(TranslatorInterface::class)->translate('services.revert-cancel-subscription.failed'));

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
        self::assertNotNull($subscription->cancel_date);
    }

    private function createFreeRedirectSubscription(): Subscription
    {
        $freeRedirectProduct = new ProductFactory()->freeRedirect()->createOne();

        return new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeRedirectProduct)
            ->forDomain($this->domainName)
            ->createOne();
    }

    private function createDnsSubscription(): Subscription
    {
        $dnsProduct = new ProductFactory()->freeDns()->createOne();

        return new SubscriptionFactory()
            ->for($dnsProduct)
            ->for($this->customer)
            ->forDomain($this->domainName)
            ->createOne();
    }
}
