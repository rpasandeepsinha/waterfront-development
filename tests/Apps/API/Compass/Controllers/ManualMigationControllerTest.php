<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\ManualMigrationController;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

#[CoversClass(ManualMigrationController::class)]
class ManualMigationControllerTest extends IntegrationTestCase
{
    #[Test]
    public function migrateSuccessful(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        $today = CarbonImmutable::now();

        $this->app->bind(ManualMigrationService::class, fn () => self::createStub(ManualMigrationService::class));

        $request = [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => $customer->customer_number,
            'domain_name' => 'testdomain.nl',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'start_date' => $today->format('Y-m-d'),
            'next_billing_date' => $today->addYear()->format('Y-m-d'),
            'end_date' => $today->addYear()->format('Y-m-d'),
            'source_business_unit' => 'some-business-unit',
            'reference_customer_number' => 'some-customer-number',
            'options' => [ManualMigrationOption::DNS_DO_NOTHING->value],
        ];

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.manual-migration.migrate', ['customer' => $customer->customer_number]), $request)->assertOk();
    }

    #[Test]
    public function migrateSuccessfulHosting(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->hostingBrons()->createOne();
        $today = CarbonImmutable::now();

        $this->app->bind(ManualMigrationService::class, fn () => self::createStub(ManualMigrationService::class));

        $daRequest = [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => $customer->customer_number,
            'domain_name' => 'testdomain.nl',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'start_date' => $today->format('Y-m-d'),
            'next_billing_date' => $today->addYear()->format('Y-m-d'),
            'end_date' => $today->addYear()->format('Y-m-d'),
            'source_business_unit' => 'some-business-unit',
            'reference_customer_number' => 'some-customer-number',
            'provider' => ProviderSlug::DIRECTADMIN->value,
            'username' => 'testUserName',
            'hostname' => 'testhostname.nl',
        ];

        $pleskRequest = [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => $customer->customer_number,
            'domain_name' => 'testdomain.nl',
            'product_uuid' => $product->uuid,
            'reference_product_id' => 'some-reference-product-id',
            'reference_subscription_id' => 'some-reference-product-id',
            'start_date' => $today->format('Y-m-d'),
            'next_billing_date' => $today->addYear()->format('Y-m-d'),
            'end_date' => $today->addYear()->format('Y-m-d'),
            'source_business_unit' => 'some-business-unit',
            'reference_customer_number' => 'some-customer-number',
            'plesk_customer_id' => 123,
            'provider' => ProviderSlug::PLESK->value,
            'username' => 'testUserName',
            'hostname' => 'testhostname.nl',
        ];

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.manual-migration.migrate', ['customer' => $customer->customer_number]), $daRequest)->assertOk();

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.manual-migration.migrate', ['customer' => $customer->customer_number]), $pleskRequest)->assertOk();
    }
}
