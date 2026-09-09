<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationValidateRequest;
use Waterfront\Domain\Ferry\Enums\ManualMigrationDomainProvider;
use Waterfront\Domain\Ferry\Services\ManualMigration\SubscriptionFormatter;

#[CoversClass(SubscriptionFormatter::class)]
class SubscriptionFormatterTest extends IntegrationTestCase
{
    #[Test]
    public function formatFromRequestShouldReturnFormattedArray(): void
    {
        // Not failing is fine here
        $this->expectNotToPerformAssertions();

        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => (string) $customer->customer_number,
            'domain_name' => 'bla.nl',
            'product_uuid' => $product->uuid,
        ]);

        $service = self::resolve(SubscriptionFormatter::class);
        $service->formatFromRequest($httpRequest, $customer);
    }

    #[Test]
    public function formatFromRequestWithHostingShouldReturnFormattedHostingArray(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->hostingBrons()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => (string) $customer->customer_number,
            'domain_name' => 'bla.nl',
            'product_uuid' => $product->uuid,
            'provider' => 'testDriver',
            'hostname' => 'serverTest.nl',
            'username' => 'random-customer-name',
        ]);

        $service = self::resolve(SubscriptionFormatter::class);
        $result = $service->formatFromRequest($httpRequest, $customer);
        Assert::assertCount(1, $result);
        Assert::assertArrayHasKey('hosting', $result);
    }

    #[Test]
    public function formatFromRequestWithHostingShouldReturnFormattedHostingArrayWithoutDomain(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->hostingBrons()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => (string) $customer->customer_number,
            'product_uuid' => $product->uuid,
            'provider' => 'testDriver',
            'hostname' => 'serverTest.nl',
            'username' => 'random-customer-name',
        ]);

        $service = self::resolve(SubscriptionFormatter::class);
        $result = $service->formatFromRequest($httpRequest, $customer);
        Assert::assertCount(1, $result);
        Assert::assertArrayHasKey('hosting', $result);
        Assert::assertIsArray($result['hosting']);
        Assert::assertArrayHasKey(0, $result['hosting']);
        Assert::assertIsArray($result['hosting'][0]);
        Assert::assertNull($result['hosting'][0]['domain']);
    }

    #[Test]
    public function formatFromRequestWithInvalidProductShouldFail(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'customer_number' => (string) $customer->customer_number,
            'domain_name' => 'bla.nl',
            'product_uuid' => Uuid::uuid4()->toString(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service = self::resolve(SubscriptionFormatter::class);
        $service->formatFromRequest($httpRequest, $customer);
    }

    #[Test]
    public function formatFromRequestWithInvalidProductPriceShouldFail(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationValidateRequest::create('', parameters: [
            'customer_number' => (string) $customer->customer_number,
            'domain_name' => 'bla.nl',
            'product_uuid' => Uuid::uuid4()->toString(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        $service = self::resolve(SubscriptionFormatter::class);
        $service->formatFromRequest($httpRequest, $customer);
    }

    #[Test]
    public function formatRequestWithNonDefaultDomainProvider(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()->prolongation()->for($product)->createOne();

        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'customer_number' => (string) $customer->customer_number,
            'product_uuid' => $product->uuid,
            'source_domain_provider' => ManualMigrationDomainProvider::OPENPROVIDER->value,
            'source_business_unit' => 'vevida',
        ]);

        $service = self::resolve(SubscriptionFormatter::class);
        $result = $service->formatFromRequest($httpRequest, $customer);
        Assert::assertArrayHasKey('domain_extensions', $result);
        Assert::assertIsArray($result['domain_extensions']);
        Assert::assertIsArray($result['domain_extensions'][0]);
        Assert::assertSame('vevida', $result['domain_extensions'][0]['reference_domain_provider_business_unit_slug']);
        Assert::assertSame(ManualMigrationDomainProvider::OPENPROVIDER->value, $result['domain_extensions'][0]['driver']);
    }
}
