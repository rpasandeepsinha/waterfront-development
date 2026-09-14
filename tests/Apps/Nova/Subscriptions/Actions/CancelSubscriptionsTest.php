<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaCancelSubscriptionsAction;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\CancellationService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaCancelSubscriptionsAction::class)]
#[AllowMockObjectsWithoutExpectations]
class CancelSubscriptionsTest extends IntegrationTestCase
{
    private ActionFields $actionFields;

    private TranslatorInterface $translator;

    private CancellationService $cancellationService;

    private SubscriptionChangeService $changeService;

    private ChangeDnsAction $changeDnsAction;

    private ChangeDnsAction&MockObject $mockChangeDnsAction;

    private Subscription $subscriptionOne;

    private Subscription $subscriptionTwo;

    private Subscription $subscriptionThree;

    private Subscription $subscriptionFour;

    private Subscription $subscriptionFive;

    private Subscription $subscriptionSix;

    private Subscription $premiumDnsSubscription;

    private LoggerInterface&mockObject $loggerMock;

    private DnsProductSpecRepository&MockObject $mockDnsProductSpecRepository;

    public function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::EXTENSION,
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $this->actionFields = self::resolve(ActionFields::class);

        $this->translator = self::resolve(TranslatorInterface::class);

        $this->cancellationService = self::resolve(CancellationService::class);

        $this->changeService = self::resolve(SubscriptionChangeService::class);

        $this->changeDnsAction = self::resolve(ChangeDnsAction::class);

        $this->mockChangeDnsAction = self::createMock(ChangeDnsAction::class);

        $this->mockDnsProductSpecRepository = self::createMock(DnsProductSpecRepository::class);

        $this->loggerMock = self::createMock(LoggerInterface::class);

        $product = new ProductFactory()->for($productGroup)->createOne();

        $groupDns = new ProductGroupFactory()->dns()->createOne();

        $freeDnsProduct = new ProductFactory()->for($groupDns)->createOne([
            'slug' => ProductType::FREE_DNS->value,
            'name' => ProductType::FREE_DNS->value,
        ]);

        new ProductPriceComponentFactory()
            ->for($freeDnsProduct)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
            ]);

        $premiumDnsProduct = new ProductFactory()->for($groupDns)->createOne([
            'slug' => ProductType::PREMIUM_DNS->value,
            'name' => ProductType::PREMIUM_DNS->value,
        ]);

        ProductSpecFactory::new()->create([
            'product_id' => $premiumDnsProduct->id,
            'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
            'value' => $freeDnsProduct->slug,
        ]);

        ProductAllowedChangeFactory::new()->downgradeChangeSupportOnly()->create([
            'from_product_id' => $premiumDnsProduct,
            'to_product_id' => $freeDnsProduct,
        ]);

        $this->subscriptionOne = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 100,
                'net_price' => 100,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]);

        $this->subscriptionTwo = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 90,
                'net_price' => 80,
                'administrative_status' => AdministrativeStatus::CANCELED->value,
            ]);

        $this->subscriptionThree = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 100,
                'net_price' => 100,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $this->subscriptionFour = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 90,
                'net_price' => 80,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $this->subscriptionFive = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 100,
                'net_price' => 100,
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            ]);

        $this->subscriptionSix = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOneQuietly([
                'next_billing_date' => CarbonImmutable::now()->addMonth(),
                'end_date' => CarbonImmutable::now()->addYear(),
                'start_date' => CarbonImmutable::now(),
                'gross_price' => 90,
                'net_price' => 80,
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            ]);

        $this->premiumDnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->administrativeStatusActive()
            ->parentSubscription($this->subscriptionThree)
            ->for($premiumDnsProduct)
            ->createOne([
                'net_price' => 100,
            ]);

        $this->actingAsCustomer(new CustomerFactory()->createOne());
    }

    #[Test]
    public function thatOnlyUpdateSubscriptionStatusActiveSuccessfully(): void
    {
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionOne->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionTwo->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscriptionThree->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscriptionFour->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscriptionFive->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscriptionSix->administrative_status);

        $subscriptions = new Collection([
            $this->subscriptionOne,
            $this->subscriptionTwo,
            $this->subscriptionThree,
            $this->subscriptionFour,
            $this->subscriptionFive,
            $this->subscriptionSix,
        ]);

        $this->mockDnsProductSpecRepository->expects(self::exactly(2))->method('isPremiumDns')->willReturn(false);

        $this->loggerMock->expects(self::never())->method('warning');

        $action = new NovaCancelSubscriptionsAction(
            $this->translator,
            $this->changeService,
            $this->changeDnsAction,
            $this->mockDnsProductSpecRepository,
            $this->loggerMock,
            $this->cancellationService,
        );
        $action->handle($this->actionFields, $subscriptions);

        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionOne->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionTwo->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionThree->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionFour->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscriptionFive->administrative_status);
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscriptionSix->administrative_status);
    }

    #[Test]
    public function thatDnsPremiumGetsCanceledWithParent(): void
    {
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscriptionThree->administrative_status);

        $subscriptions = new Collection([
            $this->subscriptionThree,
            $this->premiumDnsSubscription,
        ]);

        $this->mockChangeDnsAction->expects(self::never())->method('execute');
        $this->app->bind(ChangeDnsAction::class, fn () => $this->mockChangeDnsAction);

        $this->mockDnsProductSpecRepository
            ->expects(self::exactly(2))
            ->method('isPremiumDns')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->loggerMock->expects(self::never())->method('warning');

        $action = new NovaCancelSubscriptionsAction(
            $this->translator,
            $this->changeService,
            $this->mockChangeDnsAction,
            $this->mockDnsProductSpecRepository,
            $this->loggerMock,
            $this->cancellationService,
        );
        $action->handle($this->actionFields, $subscriptions);

        self::assertSame(AdministrativeStatus::CANCELED->value, $this->subscriptionThree->administrative_status);
        self::assertSame(AdministrativeStatus::CANCELED->value, $this->premiumDnsSubscription->administrative_status);
        self::assertSame(ProductType::PREMIUM_DNS->value, $this->premiumDnsSubscription->product->slug);
    }

    #[Test]
    public function thatDnsPremiumGetsDowngradedWithoutParent(): void
    {
        $subscriptions = new Collection([
            $this->premiumDnsSubscription,
        ]);

        $this->mockChangeDnsAction->expects(self::once())->method('execute');
        $this->app->bind(ChangeDnsAction::class, fn () => $this->mockChangeDnsAction);

        $this->mockDnsProductSpecRepository->expects(self::once())->method('isPremiumDns')->willReturn(true);

        $this->loggerMock->expects(self::never())->method('warning');

        $action = new NovaCancelSubscriptionsAction(
            $this->translator,
            $this->changeService,
            $this->mockChangeDnsAction,
            $this->mockDnsProductSpecRepository,
            $this->loggerMock,
            $this->cancellationService,
        );
        $action->handle($this->actionFields, $subscriptions);

        self::assertSame(ProductType::FREE_DNS->value, $this->premiumDnsSubscription->product->slug);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->premiumDnsSubscription->administrative_status);
    }

    #[Test]
    public function errorLogging(): void
    {
        $subscriptions = new Collection([
            $this->subscriptionOne,
            $this->premiumDnsSubscription,
            $this->subscriptionSix,
        ]);

        $changeServiceMock = self::createMock(SubscriptionChangeService::class);
        $changeActionMock = self::createMock(ChangeDnsAction::class);

        $this->mockDnsProductSpecRepository->expects(self::exactly(1))->method('isPremiumDns')->willReturn(true);

        $testException = new SubscriptionChangeException();
        $changeServiceMock
            ->expects(self::exactly(1))
            ->method('getAvailableDowngradeWhenCanceled')
            ->with($this->premiumDnsSubscription)
            ->willThrowException($testException);

        $action = new NovaCancelSubscriptionsAction(
            $this->translator,
            $changeServiceMock,
            $changeActionMock,
            $this->mockDnsProductSpecRepository,
            $this->loggerMock,
            $this->cancellationService,
        );
        $action->handle($this->actionFields, $subscriptions);
    }
}
