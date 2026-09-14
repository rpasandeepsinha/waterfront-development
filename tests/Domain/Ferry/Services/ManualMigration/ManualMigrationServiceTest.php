<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services\ManualMigration;

use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Ferry\Exceptions\MigratedCustomerValidationAndCreationException;
use Waterfront\Domain\Ferry\Models\MigratedSubscriptionSteps;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualMigrationService;
use Waterfront\Domain\Ferry\Services\ManualMigration\ManualTechnicalMigrationsService;
use Waterfront\Domain\Ferry\Services\ManualMigration\MigratedCustomerService;
use Waterfront\Domain\Ferry\Services\ManualMigration\SubscriptionFormatter;
use Waterfront\Domain\Ferry\Services\ManualMigration\SubscriptionService;
use Waterfront\Domain\Ferry\Services\ManualMigration\TechnicalSteps;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(ManualMigrationService::class)]
class ManualMigrationServiceTest extends IntegrationTestCase
{
    #[Test]
    public function migrateSuccessfully(): void
    {
        $customer = new CustomerFactory()->createOne();
        $nlProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($nlProduct)
            ->createOne();
        new ProviderFactory()->domainPlaceholder()->createOne();

        $dnsProduct = new ProductFactory()
            ->freeDns()
            ->createOne([
                'name' => 'free-dns',
                'slug' => 'free-dns',
            ]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);

        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'domain_name' => 'bla.nl',
            'product_uuid' => $nlProduct->uuid,
            'reference_subscription_id' => 'dit-is-een-id',
            'reference_customer_number' => '123',
            'source_business_unit' => 'versio',
        ]);

        // Disable job dispatching so we're not executing technical steps in this test
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $domainService = self::createStub(RtrService::class);
        $domainService->method('fetchDomain')->willReturn(self::createStub(DomainDetailsDTO::class));

        $service = new ManualMigrationService(
            self::resolve(SubscriptionService::class),
            self::resolve(MigratedCustomerService::class),
            self::resolve(SubscriptionFormatter::class),
            self::resolve(StoreNoteAction::class),
            self::resolve(TechnicalSteps::class),
            self::resolve(ManualTechnicalMigrationsService::class),
            $domainService,
        );
        $service->migrate($httpRequest, $customer, []);

        $customer->refresh();

        self::assertCount(2, $customer->subscriptions);

        $domainSubscription = $customer->subscriptions->where('product_uuid', $nlProduct->uuid)->firstOrFail();
        self::assertSame('bla.nl', $domainSubscription->domain);
        self::assertSame($customer->id, $domainSubscription->customer_id);

        self::assertCount(1, $customer->migratedCustomers);

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();
        self::assertTrue($migratedCustomer->administrative_successful);
        self::assertTrue($migratedCustomer->billing_successful);
        self::assertTrue($migratedCustomer->enable_invoicing);
        self::assertSame('manual_migration', $migratedCustomer->group_type);

        self::assertGreaterThan(
            0,
            MigratedSubscriptionSteps::where('subscription_id', $domainSubscription->id)->count(),
        );
    }

    #[Test]
    public function migrateSuccessfullyWithHostingAlsoGivesPayload(): void
    {
        $customer = new CustomerFactory()->createOne();
        $hostingBrons = new ProductFactory()->hostingBrons()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($hostingBrons)
            ->createOne();
        new ProviderFactory()->hostingPlaceholder()->createOne();
        new ProviderFactory()->hostingDirectAdmin()->createOne();

        $dnsProduct = new ProductFactory()
            ->freeDns()
            ->createOne([
                'name' => 'free-dns',
                'slug' => 'free-dns',
            ]);
        new ProductPriceComponentFactory()
            ->for($dnsProduct)
            ->registration()
            ->createOne(['price' => 0]);

        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'billing_period' => 12,
            'contract_period' => 12,
            'domain_name' => 'bla.nl',
            'product_uuid' => $hostingBrons->uuid,
            'hostname' => 'bla.nl',
            'provider' => 'directadmin',
            'username' => 'administrator',
            'reference_subscription_id' => 'dit-is-een-id',
            'reference_customer_number' => '123',
            'source_business_unit' => 'versio',
        ]);

        // Disable job dispatching so we're not executing technical steps in this test
        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));

        $manualTechnicalMigrationService = self::createMock(ManualTechnicalMigrationsService::class);
        $manualTechnicalMigrationService
            ->expects(self::once())
            ->method('fireNextStep')
            ->with(self::anything(), self::callback(function (array $data) {
                self::assertIsArray($data[0]);
                self::assertArrayHasKey('driver', $data[0]);
                self::assertSame(ProviderSlug::DIRECTADMIN->value, $data[0]['driver']);

                return true;
            }));

        $service = new ManualMigrationService(
            self::resolve(SubscriptionService::class),
            self::resolve(MigratedCustomerService::class),
            self::resolve(SubscriptionFormatter::class),
            self::resolve(StoreNoteAction::class),
            self::resolve(TechnicalSteps::class),
            $manualTechnicalMigrationService,
            self::resolve(RtrService::class),
        );

        $service->migrate($httpRequest, $customer, []);

        $customer->refresh();

        self::assertCount(1, $customer->subscriptions);
    }

    #[Test]
    public function migrateCreatesMigratedCustomerAndSetsAdministrativeMigrated(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();

        $subscriptionService = self::createStub(SubscriptionService::class);
        $subscriptionService
            ->method('storeSubscription')
            ->willReturn(
                new SubscriptionFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
            );

        $domainService = self::createStub(RtrService::class);
        $domainService->method('fetchDomain')->willReturn(self::createStub(DomainDetailsDTO::class));

        $service = new ManualMigrationService(
            $subscriptionService,
            self::resolve(MigratedCustomerService::class),
            self::createStub(SubscriptionFormatter::class),
            self::createStub(StoreNoteAction::class),
            self::resolve(TechnicalSteps::class),
            self::createStub(ManualTechnicalMigrationsService::class),
            $domainService,
        );
        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'domain_name' => 'bla.nl',
            'product_uuid' => $product->uuid,
            'reference_subscription_id' => 'dit-is-een-id',
            'reference_customer_number' => '123',
            'source_business_unit' => 'versio',
        ]);
        $service->migrate($httpRequest, $customer, []);

        $customer->refresh();

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        self::assertCount(1, $customer->migratedCustomers);
        self::assertTrue($migratedCustomer->administrative_successful);
    }

    #[Test]
    public function migrateWithInternalCommentShouldStoreNote(): void
    {
        $customer = new CustomerFactory()->createOne();
        $product = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->prolongation()
            ->for($product)
            ->createOne();
        $httpRequest = ManualMigrationMigrateRequest::create('', parameters: [
            'domain_name' => 'bla.nl',
            'product_uuid' => $product->uuid,
            'reference_subscription_id' => 'dit-is-een-id',
            'reference_customer_number' => '123',
            'internal_comment' => 'test',
            'source_business_unit' => 'versio',
        ]);

        $subscriptionService = self::createStub(SubscriptionService::class);
        $subscriptionService
            ->method('storeSubscription')
            ->willReturn(
                new SubscriptionFactory()
                    ->for($customer)
                    ->for($product)
                    ->createOne(),
            );

        $domainService = self::createStub(RtrService::class);
        $domainService->method('fetchDomain')->willReturn(self::createStub(DomainDetailsDTO::class));

        $service = new ManualMigrationService(
            $subscriptionService,
            self::resolve(MigratedCustomerService::class),
            self::createStub(SubscriptionFormatter::class),
            self::resolve(StoreNoteAction::class),
            self::resolve(TechnicalSteps::class),
            self::createStub(ManualTechnicalMigrationsService::class),
            $domainService,
        );

        $service->migrate($httpRequest, $customer, []);

        $customer->refresh();
        $note = $customer->notes->firstOrFail();

        self::assertSame('test', $note->note);
    }

    #[Test]
    public function migrateAlreadyMigratedCustomerShouldThrowException(): void
    {
        $this->expectException(MigratedCustomerValidationAndCreationException::class);

        $customer = new CustomerFactory()->createOne();
        $migrationCustomer = new MigratedCustomersFactory()->createOne();
        $migrationCustomer->customers()->attach($customer);

        $service = self::resolve(ManualMigrationService::class);
        $service->migrate(new ManualMigrationMigrateRequest(), $customer, []);
    }
}
