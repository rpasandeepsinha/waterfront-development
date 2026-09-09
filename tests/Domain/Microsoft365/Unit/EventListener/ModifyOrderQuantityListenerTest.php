<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\Office365\Entity\CloudLicense;
use SandwaveIo\Office365\Entity\OrderModifyQuantity;
use SandwaveIo\Office365\Entity\Terminate;
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
use Waterfront\Domain\Microsoft365\EventListener\ModifyOrderQuantityListener;
use Waterfront\Domain\Microsoft365\EventListener\TerminateOrderListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(ModifyOrderQuantityListener::class)]
class ModifyOrderQuantityListenerTest extends IntegrationTestCase
{
    private Product $childProduct;

    private Subscription $parentSubscription;

    private Microsoft365Deployment $microsoft365Deployment;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();
        $parentProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard-parent',
        ]);

        $this->childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $this->parentSubscription = new SubscriptionFactory()->withCustomer()->for($parentProduct)->createOne();

        new SubscriptionFactory()->for($this->childProduct)->for($customer)->parentSubscription($this->parentSubscription)->createOne([
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()->for($this->parentSubscription)->for($microsoft365CustomerInfo)->createOne([
            'kpn_order_id' => 123,
            'kpn_status' => Microsoft365OrderStatus::ACTIVE,
        ]);
    }

    #[Test]
    public function modifyOrderQuantityIncrease(): void
    {
        $numberToCreate = 2;
        $numberOfSeats = 3;

        $this->createExtraChildSubscriptions($numberToCreate);
        $activeChildrenCount = $this->microsoft365Deployment->subscription->children->where('technical_status', TechnicalStatus::OK->value)->count();

        self::assertSame(Microsoft365OrderStatus::ACTIVE, $this->microsoft365Deployment->kpn_status);
        self::assertSame($activeChildrenCount, $numberOfSeats);

        // modify the quantity by adding 1 extra, this is the waterfront part
        // it creates new child subscription with the modify_pending status
        new SubscriptionFactory()->withCustomer()->count(1)->for($this->childProduct)->parentSubscription($this->parentSubscription)->createOne([
            'technical_status' => TechnicalStatus::REGISTRATION->value,
        ]);

        // create modify order quantity entity to change the seats to a total of 3
        $modifyOrderQuantity = $this->createModifyOrderQuantity($this->microsoft365Deployment, 1, true, 124);
        $status = (new Status('Modified', []));

        // KPN will first send a Modified Response
        new ModifyOrderQuantityListener()->execute($modifyOrderQuantity, $status);

        // refresh the subscription so we have the correct order id
        // the order id is updated with the upgrade order id after the ModifyOrderQuantityListener::execute()
        $this->microsoft365Deployment->refresh();

        // the terminate order response will contain the "old" order id and not the upgrade order id
        $terminate = $this->createTerminateOrder($this->microsoft365Deployment->kpn_order_id);
        $status = (new Status('Success', []));

        // After the modified response we will get a terminate and create response
        new TerminateOrderListener()->execute($terminate, $status);

        $cloudLicense = $this->createCloudLicense($this->microsoft365Deployment->kpn_order_id, 3);
        $status = (new Status('Accepted', []));

        // After the modified response we will get a terminate and create response
        new CloudLicenseListener(self::createStub(Microsoft365Service::class))->execute($cloudLicense, $status);

        $this->microsoft365Deployment->refresh();
        $okChildrenCount = $this->microsoft365Deployment->subscription->children->where('technical_status', TechnicalStatus::OK->value)->count();
        $activeChildrenCount = $this->microsoft365Deployment->subscription->children->where('administrative_status', AdministrativeStatus::ACTIVE->value)->count();
        $archivedChildrenCount = $this->microsoft365Deployment->subscription->children->where('administrative_status', AdministrativeStatus::ARCHIVED->value)->count();
        $archivingChildrenCount = $this->microsoft365Deployment->subscription->children->where('administrative_status', AdministrativeStatus::ARCHIVING->value)->count();

        self::assertSame($modifyOrderQuantity->getUpgradeOrderId(), $this->microsoft365Deployment->kpn_order_id);
        self::assertSame(Microsoft365OrderStatus::ACTIVE, $this->microsoft365Deployment->kpn_status);
        self::assertSame(4, $okChildrenCount);
        self::assertSame($activeChildrenCount, $numberToCreate);
        self::assertSame($archivedChildrenCount, $numberToCreate);
        self::assertSame(0, $archivingChildrenCount);
    }

    #[Test]
    public function modifyOrderQuantityDecrease(): void
    {
        $numberToDelete = 1;
        $numberOfSeats = 2;

        $this->createExtraChildSubscriptions($numberToDelete);

        // pretend we terminated 1 seat from the waterfront UI and there is a child subscription with technical status "archived'
        $this->microsoft365Deployment->subscription->children
            ->where('technical_status', TechnicalStatus::OK->value)->first
            ->update(
                [
                'technical_status' => TechnicalStatus::DELETED->value,
                ]
            );

        // since 1 is archived, there also should be only active
        self::assertSame(
            $this->microsoft365Deployment->subscription->children->where('technical_status', TechnicalStatus::OK->value)->count(),
            $numberOfSeats - $numberToDelete
        );

        // create modify order quantity entity to reflect the seats to a total of 1
        $modifyOrderQuantity = $this->createModifyOrderQuantity($this->microsoft365Deployment, $numberToDelete, true);
        $status = (new Status('Modified', []));

        // KPN will first send a Modified Response
        new ModifyOrderQuantityListener()->execute($modifyOrderQuantity, $status);

        // refresh the subscription so we have the correct order id
        // the order id is updated with the upgrade order id after the ModifyOrderQuantityListener::execute()
        $this->microsoft365Deployment->refresh();

        // the terminate order response will contain the "old" order id and not the upgrade order id
        $terminate = $this->createTerminateOrder($this->microsoft365Deployment->kpn_order_id);
        $status = (new Status('Success', []));

        // After the modified response we will get a terminate and create response
        new TerminateOrderListener()->execute($terminate, $status);

        $cloudLicense = $this->createCloudLicense($this->microsoft365Deployment->kpn_order_id, 1);
        $status = (new Status('Accepted', []));

        // After the modified response we will get a terminate and create response
        new CloudLicenseListener(self::createStub(Microsoft365Service::class))->execute($cloudLicense, $status);

        $this->microsoft365Deployment->refresh();
        $activeChildrenCount = $this->microsoft365Deployment->subscription->children->where('technical_status', TechnicalStatus::OK->value)->count();

        self::assertSame($modifyOrderQuantity->getUpgradeOrderId(), $this->microsoft365Deployment->kpn_order_id);
        self::assertSame(Microsoft365OrderStatus::MODIFIED, $this->microsoft365Deployment->kpn_status);
        self::assertSame(1, $activeChildrenCount);
    }

    private function createExtraChildSubscriptions(int $amount): void
    {
        new SubscriptionFactory()->withCustomer()->for($this->childProduct)->parentSubscription($this->parentSubscription)->state([
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::ARCHIVING->value,
        ])->createMany($amount);
    }

    private function createModifyOrderQuantity(Microsoft365Deployment $microsoft365Deployment, int $numberOfSeats, bool $isDelta, ?int $kpnUpgradeOrderId = null): OrderModifyQuantity
    {
        $data = [
            'OrderId' => $microsoft365Deployment->kpn_order_id,
            'IsDelta' => $isDelta,
            'Quantity' => $numberOfSeats,
            'Header' => [
                'PartnerReference' => sprintf('WF-ORDER-123-%d', $this->microsoft365Deployment->id),
            ],
        ];

        if ($kpnUpgradeOrderId !== null) {
            $data['UpgradeOrderId'] = $kpnUpgradeOrderId;
        }

        $orderModifyQuantity = EntityHelper::deserializeArray(OrderModifyQuantity::class, $data);

        self::assertInstanceOf(OrderModifyQuantity::class, $orderModifyQuantity);

        return $orderModifyQuantity;
    }

    private function createCloudLicense(?int $kpnOrderId, int $numberOfSeats = 2): CloudLicense
    {
        $cloudLicense = EntityHelper::deserializeArray(CloudLicense::class, [
            'OrderId' => $kpnOrderId,
            'Quantity' => $numberOfSeats,
        ]);

        self::assertInstanceOf(CloudLicense::class, $cloudLicense);

        return $cloudLicense;
    }

    private function createTerminateOrder(?int $kpnOrderId): Terminate
    {
        $terminate = EntityHelper::deserializeArray(Terminate::class, [
            'OrderId' => 'OID' . $kpnOrderId,
        ]);

        self::assertInstanceOf(Terminate::class, $terminate);

        return $terminate;
    }
}
