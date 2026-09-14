<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Microsoft365;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\Microsoft365Controller;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;

#[CoversClass(Microsoft365Controller::class)]
class Microsoft365GatherMicrosoftDataTest extends IntegrationTestCase
{
    #[Test]
    public function microsoftInformation(): void
    {
        $customer = new CustomerFactory()->createOne();

        $group = new ProductGroupFactory()->microsoft365()->createOne();

        $parentProduct = new ProductFactory()->createOne([
            'slug' => 'microsoft-business-standard-parent',
            'product_group_id' => $group->id,
        ]);

        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($parentProduct)
            ->createOne();

        new ProductFactory()->createOne([
            'slug' => 'microsoft-business-standard',
            'product_group_id' => $group->id,
        ]);

        $tenantId = 'dc76190d-67f4-4bf6-90ae-3ccb00ae3523';
        $customerInfo = new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $customer->id,
            'tenant_name' => $customer->customer_number . '.onmicrosoft.com',
            'tenant_id' => $tenantId,
            'primary_domain' => 'yourhosting.nl',
            'primary_domain_status' => PrimaryDomainStatus::VERIFICATION_PENDING,
            'mca_signed_at' => null,
        ]);

        $parentSubscription = new SubscriptionFactory()->makeOne([
            'customer_id' => $customer->id,
        ]);

        $parentProduct->subscriptions()->save($parentSubscription);

        $microsoftDeployment = new Microsoft365DeploymentFactory()->createOne([
            'subscription_id' => $parentSubscription->id,
            'microsoft365_customer_info_id' => $customerInfo->id,
        ]);

        new SubscriptionFactory()->state([
            'customer_id' => $customer->id,
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $parentProduct->uuid,
        ])->createMany(4);

        new SubscriptionFactory()->state([
            'customer_id' => $customer->id,
            'parent_subscription_id' => $parentSubscription->id,
            'product_uuid' => $parentProduct->uuid,
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'cancel_date' => new CarbonImmutable()->subDay(),
        ])->createMany(2);

        $response = $this->actingAsCustomer($customer)->getJson(
            $this->generateRoute('partners.microsoft365.microsoft-information'),
        );
        $response->assertOk();

        /** @var object{data: object{tenant_name: string, tenant_id: string, primary_domain: string, primary_domain_status: string, deployments: array<int, object>, available_actions: string[]}} $data */
        $data = json_decode((string) $response->getContent());
        $data = $data->data;
        self::assertSame($customer->customer_number . '.onmicrosoft.com', $data->tenant_name);
        self::assertSame($tenantId, $data->tenant_id);
        self::assertSame('yourhosting.nl', $data->primary_domain);
        self::assertSame(PrimaryDomainStatus::VERIFICATION_PENDING->value, $data->primary_domain_status);
        self::assertCount(1, $data->deployments);
        self::assertSame('sign_mca', $data->available_actions[0]);

        /** @var object{id: int, subscription_uuid: string, administrative_status: string, technical_status: string, start_date: string, end_date: string, period: int, seat_count: int, canceled_seat_count: int} $deployment */
        $deployment = $data->deployments[0];
        self::assertSame($microsoftDeployment->subscription->id, $deployment->id);
        self::assertSame($microsoftDeployment->subscription->uuid, $deployment->subscription_uuid);
        self::assertSame($microsoftDeployment->subscription->administrative_status, $deployment->administrative_status);
        self::assertSame($microsoftDeployment->subscription->technical_status, $deployment->technical_status);
        self::assertSame(
            $microsoftDeployment->subscription->start_date->format(DateTimeInterface::ATOM),
            $deployment->start_date,
        );
        self::assertSame(
            $microsoftDeployment->subscription->end_date->format(DateTimeInterface::ATOM),
            $deployment->end_date,
        );
        self::assertSame($microsoftDeployment->subscription->contract_period, $deployment->period);
        self::assertSame(4, $deployment->seat_count);
        self::assertSame(2, $deployment->canceled_seat_count);
    }
}
