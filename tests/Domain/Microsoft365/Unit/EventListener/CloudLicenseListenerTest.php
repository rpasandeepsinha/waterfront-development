<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\Office365\Entity\CloudLicense;
use SandwaveIo\Office365\Helper\EntityHelper;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\EventListener\CloudLicenseListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(CloudLicenseListener::class)]
class CloudLicenseListenerTest extends IntegrationTestCase
{
    private const int ORDER_ID = 42;

    private ProductGroup $productGroup;

    private Subscription $subscription;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Microsoft365Deployment $microsoft365Deployment;

    private Microsoft365Service&MockObject $microsoft365Service;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $customer = new CustomerFactory()->createOne();

        $this->productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($parentProduct)
            ->createOne();

        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne([
            'kpn_customer_id' => 'CID123',
            'tenant_id' => null,
        ]);

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($this->subscription)
            ->for($this->microsoft365CustomerInfo)
            ->createOne();

        $this->microsoft365Service = self::createMock(Microsoft365Service::class);
    }

    #[Test]
    public function acceptedCloudLicenseEvent(): void
    {
        $cloudLicense = $this->createCloudLicense(20);

        $status = new Status(Microsoft365OrderStatus::ACCEPTED->value, []);

        $this->microsoft365Service->expects(self::never())->method('retryPendingCopilotOrder');

        new CloudLicenseListener($this->microsoft365Service)->execute($cloudLicense, $status);
        $this->microsoft365Deployment->refresh();

        self::assertSame(self::ORDER_ID, (int) $this->microsoft365Deployment->kpn_order_id);
        self::assertSame(Microsoft365OrderStatus::ACCEPTED, $this->microsoft365Deployment->kpn_status);
    }

    #[Test]
    public function activeCloudLicenseEvent(): void
    {
        $quantity = 3;

        $cloudLicense = $this->createCloudLicense(3);

        $childProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for($childProduct)
            ->parentSubscription($this->subscription)
            ->state([
                'technical_status' => TechnicalStatus::REGISTRATION->value,
            ])
            ->createMany($quantity);

        self::assertNull($this->microsoft365Deployment->kpn_start_date);

        $status = new Status(Microsoft365OrderStatus::ACCEPTED->value, []);

        $this->microsoft365Service
            ->expects(self::once())
            ->method('retryPendingCopilotOrder')
            ->with(self::callback(
                fn (Microsoft365CustomerInfo $microsoft365CustomerInfo): bool => (
                    $microsoft365CustomerInfo->id === $this->microsoft365CustomerInfo->id
                ),
            ));

        $listener = new CloudLicenseListener($this->microsoft365Service);
        $listener->execute($cloudLicense, $status);
        $this->microsoft365Deployment->refresh();

        $status = new Status(Microsoft365OrderStatus::ACTIVE->value, []);
        $listener->execute($cloudLicense, $status);

        $this->microsoft365Deployment->refresh();
        $this->microsoft365CustomerInfo->refresh();

        $countActiveSeats = Subscription::where([
            'parent_subscription_id' => $this->subscription->id,
            'technical_status' => TechnicalStatus::OK->value,
        ])->count();

        self::assertNotNull($this->microsoft365Deployment->kpn_start_date);

        self::assertSame(Microsoft365OrderStatus::ACTIVE, $this->microsoft365Deployment->kpn_status);
        self::assertSame(self::ORDER_ID, $this->microsoft365Deployment->kpn_order_id);
        self::assertSame('2023-01-01 00:00:00', $this->microsoft365Deployment->kpn_start_date->toDateTimeString());
        self::assertSame($quantity, $countActiveSeats);
    }

    #[Test]
    public function activeCloudLicenseEventDoesNotRetryCopilotWhenSeatsAreMissing(): void
    {
        $cloudLicense = $this->createCloudLicense(3);

        $childProduct = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for($childProduct)
            ->parentSubscription($this->subscription)
            ->state([
                'technical_status' => TechnicalStatus::REGISTRATION->value,
            ])
            ->createMany(2);

        $this->microsoft365Service->expects(self::never())->method('retryPendingCopilotOrder');

        $listener = new CloudLicenseListener($this->microsoft365Service);
        $listener->execute($cloudLicense, new Status(Microsoft365OrderStatus::ACCEPTED->value, []));
        $listener->execute($cloudLicense, new Status(Microsoft365OrderStatus::ACTIVE->value, []));

        self::assertSame(TechnicalStatus::ERROR->value, $this->subscription->refresh()->technical_status);
    }

    private function createCloudLicense(int $quantity): CloudLicense
    {
        $cloudLicense = EntityHelper::deserializeArray(CloudLicense::class, [
            'CustomerId' => $this->microsoft365CustomerInfo->kpn_customer_id,
            'Quantity' => $quantity,
            'OrderId' => self::ORDER_ID,
            'Header' => [
                'PartnerReference' => sprintf('WF-ORDER-123-%d', $this->microsoft365Deployment->id),
                'DateCreated' => '2023-01-01T00:00:00',
            ],
        ]);

        self::assertInstanceOf(CloudLicense::class, $cloudLicense);

        return $cloudLicense;
    }
}
