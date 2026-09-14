<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Unit\EventListener;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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
use Waterfront\Domain\Microsoft365\EventListener\TerminateOrderListener;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(TerminateOrderListener::class)]
class TerminateOrderListenerTest extends IntegrationTestCase
{
    private Subscription $subscription;

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

        $childProduct = new ProductFactory()->for($productGroup)->createOne([
            'slug' => 'microsoft-business-standard',
        ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($parentProduct)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ARCHIVING->value,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->count(3)
            ->for($childProduct)
            ->parentSubscription($this->subscription)
            ->createOne([
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ARCHIVING->value,
            ]);

        $customerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne();

        $this->microsoft365Deployment = new Microsoft365DeploymentFactory()
            ->for($customerInfo)
            ->for($this->subscription)
            ->createOne();
    }

    #[Test]
    public function terminateFromWaterfront(): void
    {
        $this->triggerTerminateOrderListener();

        $children = $this->subscription->children;

        // Shows it has set all subscriptions on deleted.
        self::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscription->administrative_status);
        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
        foreach ($children as $child) {
            self::assertSame(AdministrativeStatus::ARCHIVED->value, $child->administrative_status);
            self::assertSame(TechnicalStatus::DELETED->value, $child->technical_status);
        }

        self::assertSame(Microsoft365OrderStatus::TERMINATED, $this->microsoft365Deployment->kpn_status);
    }

    private function triggerTerminateOrderListener(): void
    {
        $data = [
            'TerminateAsSoonAsPossible' => true,
            'DesiredTerminateDate' => CarbonImmutable::now()->format(DateTimeFormat::DATE),
            'OrderId' => 'OID' . $this->microsoft365Deployment->kpn_order_id,
        ];

        $terminate = EntityHelper::deserializeArray(Terminate::class, $data);

        self::assertInstanceOf(Terminate::class, $terminate);

        $status = new Status('207', []);

        new TerminateOrderListener()->execute($terminate, $status);

        $this->subscription->refresh();
        $this->microsoft365Deployment->refresh();
    }
}
