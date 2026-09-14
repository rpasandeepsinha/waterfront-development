<?php

declare(strict_types=1);

namespace Tests\Domain\ManualProvisioning\Unit;

use Generator;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledManualSubscriptionEmployee;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledReminderManualSubscription;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ManualProvisioningService::class)]
class ManualProvisioningServiceTest extends IntegrationTestCase
{
    private ManualProvisioningService $manualProvisioningService;

    private Subscription $subscription;

    private ProductGroup $productGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manualProvisioningService = self::resolve(ManualProvisioningService::class);

        $this->productGroup = new ProductGroupFactory()
            ->manualSubscription()
            ->createOne([
                'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
            ]);

        $product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct',
            'name' => 'ManualTestProduct',
            'product_group_id' => $this->productGroup->id,
        ]);

        $this->subscription = new SubscriptionFactory()->for(new CustomerFactory()->createOne())->createOne([
            'product_uuid' => $product->uuid,
        ]);
    }

    #[Test]
    public function sendCreationNotificationSuccess(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::isInstanceOf(OrderedManualSubscriptionEmployee::class),
            );
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualProvisioningService = self::resolve(ManualProvisioningService::class);

        new TemplateFactory()->createOne([
            'slug' => OrderedManualSubscriptionEmployee::getTemplateSlug(),
        ]);

        $manualProvisioningService->sendCreationNotification($this->subscription);
        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->technical_status);
    }

    #[Test]
    public function sendTerminationNotificationSuccess(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::isInstanceOf(CanceledManualSubscriptionEmployee::class),
            );
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualProvisioningService = self::resolve(ManualProvisioningService::class);

        new TemplateFactory()->createOne([
            'slug' => CanceledManualSubscriptionEmployee::getTemplateSlug(),
        ]);

        $manualProvisioningService->sendTerminationNotification($this->subscription);
    }

    #[Test]
    public function sendTerminationReminderNotificationSuccess(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::isInstanceOf(CanceledReminderManualSubscription::class),
            );
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualProvisioningService = self::resolve(ManualProvisioningService::class);

        new TemplateFactory()->createOne([
            'slug' => CanceledReminderManualSubscription::getTemplateSlug(),
        ]);

        $manualProvisioningService->sendTerminationReminderNotification($this->subscription);
    }

    #[Test]
    public function sendActivatedNotificationSuccess(): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(
                self::anything(),
                self::isInstanceOf(ActivatedManualSubscriptionCustomer::class),
            );
        $this->app->bind(Mailer::class, fn () => $mailer);

        $manualProvisioningService = self::resolve(ManualProvisioningService::class);
        new TemplateFactory()->createOne([
            'slug' => ActivatedManualSubscriptionCustomer::getTemplateSlug(),
        ]);

        $manualProvisioningService->sendActivationNotification($this->subscription);
    }

    #[DataProvider('emailDataProvider')]
    #[Test]
    public function sendCreationNotificationFailDueInvalidArgument(?string $configVar): void
    {
        Config::set('manual-provisioning.notification-email', $configVar);

        $this->expectException(InvalidArgumentException::class);
        $this->manualProvisioningService->sendCreationNotification($this->subscription);
    }

    #[Test]
    public function manualProductIsActivateSuccess(): void
    {
        $product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct2',
            'name' => 'ManualTestProduct',
            'product_group_id' => $this->productGroup->id,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->technicalStatusOk()
            ->createOne();

        self::assertTrue($this->manualProvisioningService->manualProductIsActivate($subscription));
    }

    #[Test]
    public function manualProductIsActivateFailedDueWrongStatus(): void
    {
        $product = new ProductFactory()->createOne([
            'slug' => 'manual-testproduct3',
            'name' => 'ManualTestProduct',
            'product_group_id' => $this->productGroup->id,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->createOne();

        self::assertFalse($this->manualProvisioningService->manualProductIsActivate($subscription));
    }

    #[Test]
    public function manualProductIsActivateFailedDueWrongProductGroup(): void
    {
        $productGroup = new ProductGroupFactory()
            ->manualSubscription()
            ->createOne([
                'slug' => ProductGroupType::EXTENSION,
            ]);

        $product = new ProductFactory()->createOne([
            'slug' => 'wrong-testproduct',
            'name' => 'WrongProduct',
            'product_group_id' => $productGroup->id,
        ]);

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->technicalStatusOk()
            ->createOne();

        self::assertFalse($this->manualProvisioningService->manualProductIsActivate($subscription));
    }

    /**
     * @return Generator<mixed>
     */
    public static function emailDataProvider(): Generator
    {
        yield [''];
        yield [' '];
    }
}
