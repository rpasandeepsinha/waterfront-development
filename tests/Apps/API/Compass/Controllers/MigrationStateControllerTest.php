<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\MigrationStateController;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(MigrationStateController::class)]
class MigrationStateControllerTest extends IntegrationTestCase
{
    #[Test]
    public function listOnlyReturnsSubscriptionsThatCameOutOfAMigration(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $migratedCustomer = new CustomerFactory()->createOne();
        $migratedCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $migrated = new SubscriptionFactory()
            ->for($product)
            ->for($migratedCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $migrated->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        new SubscriptionFactory()
            ->for($product)
            ->for(new CustomerFactory()->createOne())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('not-migrated.nl')
            ->createOne();

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query(['orderBy' => ['domain_asc']]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-extension.nl');
    }

    #[Test]
    public function listSearchMatchesTheDomain(): void
    {
        $extensionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);
        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'name' => 'Hosting Brons',
            'slug' => 'hosting_brons',
        ]);

        $extensionCustomer = new CustomerFactory()->createOne();
        $extensionCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $extension = new SubscriptionFactory()
            ->for($extensionProduct)
            ->for($extensionCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $extension->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $hostingCustomer = new CustomerFactory()->createOne();
        $hostingCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $hosting = new SubscriptionFactory()
            ->for($hostingProduct)
            ->for($hostingCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $hosting->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query(['search' => 'migrated-hosting']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl');
    }

    #[Test]
    public function listSearchMatchesTheProductName(): void
    {
        $extensionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);
        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'name' => 'Hosting Brons',
            'slug' => 'hosting_brons',
        ]);

        $extensionCustomer = new CustomerFactory()->createOne();
        $extensionCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $extension = new SubscriptionFactory()
            ->for($extensionProduct)
            ->for($extensionCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $extension->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $hostingCustomer = new CustomerFactory()->createOne();
        $hostingCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $hosting = new SubscriptionFactory()
            ->for($hostingProduct)
            ->for($hostingCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $hosting->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query(['search' => 'hosting brons']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl');
    }

    #[Test]
    public function listSearchMatchesTheBatchGroup(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $batchOneCustomer = new CustomerFactory()->createOne();
        $batchOneCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['group_type' => 'batch-one']),
            );

        $batchOne = new SubscriptionFactory()
            ->for($product)
            ->for($batchOneCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $batchOne->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $batchTwoCustomer = new CustomerFactory()->createOne();
        $batchTwoCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['group_type' => 'batch-two']),
            );

        $batchTwo = new SubscriptionFactory()
            ->for($product)
            ->for($batchTwoCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $batchTwo->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?' . http_build_query(['search' => 'batch-one']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-extension.nl');
    }

    #[Test]
    public function listSearchMatchesTheReferenceSubscriptionId(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $extensionCustomer = new CustomerFactory()->createOne();
        $extensionCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $extension = new SubscriptionFactory()
            ->for($product)
            ->for($extensionCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $extension
            ->migratedSubscriptions()
            ->attach(
                new MigratedSubscriptionsFactory()->createOne([
                    'reference_subscription_id' => 'reference-migrated-extension.nl',
                ]),
            );

        $hostingCustomer = new CustomerFactory()->createOne();
        $hostingCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $hosting = new SubscriptionFactory()
            ->for($product)
            ->for($hostingCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $hosting
            ->migratedSubscriptions()
            ->attach(
                new MigratedSubscriptionsFactory()->createOne([
                    'reference_subscription_id' => 'reference-migrated-hosting.nl',
                ]),
            );

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'search' => 'reference-migrated-hosting.nl',
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl');
    }

    #[Test]
    public function listFiltersOnProductGroup(): void
    {
        $extensionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);
        $hostingProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne([
            'name' => 'Hosting Brons',
            'slug' => 'hosting_brons',
        ]);

        $extensionCustomer = new CustomerFactory()->createOne();
        $extensionCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $extension = new SubscriptionFactory()
            ->for($extensionProduct)
            ->for($extensionCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $extension->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $hostingCustomer = new CustomerFactory()->createOne();
        $hostingCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $hosting = new SubscriptionFactory()
            ->for($hostingProduct)
            ->for($hostingCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $hosting->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'product_group' => ProductGroupType::HOSTING->value,
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl');
    }

    #[Test]
    public function listFiltersOnBusinessUnit(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $argewebCustomer = new CustomerFactory()->createOne();
        $argewebCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['reference_name' => 'argeweb']),
            );

        $argeweb = new SubscriptionFactory()
            ->for($product)
            ->for($argewebCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $argeweb->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $vevidaCustomer = new CustomerFactory()->createOne();
        $vevidaCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['reference_name' => 'vevida']),
            );

        $vevida = new SubscriptionFactory()
            ->for($product)
            ->for($vevidaCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $vevida->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query(['business_unit' => 'argeweb']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-extension.nl');
    }

    #[Test]
    public function listIgnoresFiltersThatWereClearedToTheirEmptyValue(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        foreach (['migrated-extension.nl', 'migrated-hosting.nl'] as $domain) {
            $customer = new CustomerFactory()->createOne();
            $customer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

            $subscription = new SubscriptionFactory()
                ->for($product)
                ->for($customer)
                ->administrativeStatusActive()
                ->technicalStatusOk()
                ->forDomain($domain)
                ->createOne();
            $subscription->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());
        }

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'product_group' => '',
                        'business_unit' => '',
                        'status' => '',
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function listFiltersOnTechnicallyFailedStatus(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $okCustomer = new CustomerFactory()->createOne();
        $okCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $ok = new SubscriptionFactory()
            ->for($product)
            ->for($okCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $ok->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $failedCustomer = new CustomerFactory()->createOne();
        $failedCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $failed = new SubscriptionFactory()
            ->for($product)
            ->for($failedCustomer)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::FAILED->value)
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $failed->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?' . http_build_query(['status' => 'failed']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl');
    }

    #[Test]
    public function listFiltersOnMigrationNotSuccessfulStatus(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $unsuccessfulCustomer = new CustomerFactory()->createOne();
        $unsuccessfulCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['successful' => false]),
            );

        $unsuccessful = new SubscriptionFactory()
            ->for($product)
            ->for($unsuccessfulCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $unsuccessful->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $successfulCustomer = new CustomerFactory()->createOne();
        $successfulCustomer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['successful' => true]),
            );

        $successful = new SubscriptionFactory()
            ->for($product)
            ->for($successfulCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $successful->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'status' => 'migration-not-successful',
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-extension.nl');
    }

    #[Test]
    public function listDoesNotDuplicateRowsWhenASubscriptionHasSeveralMigrationRecords(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $customer = new CustomerFactory()->createOne();
        $customer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['reference_name' => 'argeweb', 'group_type' => 'batch-one']),
            );
        $customer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne([
                    'reference_name' => 'argeweb',
                    'group_type' => 'batch-three',
                ]),
            );

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $subscription
            ->migratedSubscriptions()
            ->attach(
                new MigratedSubscriptionsFactory()->createOne(['reference_subscription_id' => 'first-reference']),
            );
        $subscription
            ->migratedSubscriptions()
            ->attach(
                new MigratedSubscriptionsFactory()->createOne(['reference_subscription_id' => 'second-reference']),
            );

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query(['business_unit' => 'argeweb']),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.domain', 'migrated-extension.nl');
    }

    #[Test]
    public function listRestrictsThePayloadToTheRequestedFields(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $customer = new CustomerFactory()->createOne();
        $customer
            ->migratedCustomers()
            ->attach(
                new MigratedCustomersFactory()->createOne(['reference_name' => 'argeweb']),
            );

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $subscription->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'fields' => ['domain', 'business_unit'],
                        'orderBy' => ['domain_asc'],
                    ]),
            )
            ->assertOk()
            ->assertJsonCount(4, 'data.0')
            ->assertJsonStructure(['data' => [['id', 'uuid', 'domain', 'business_unit']]])
            ->assertJsonMissingPath('data.0.product')
            ->assertJsonMissingPath('data.0.technical_status');
    }

    #[Test]
    public function listSortsOnASubscriptionColumnWhileTheProductsTableIsJoined(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        foreach (['migrated-extension.nl', 'migrated-hosting.nl'] as $domain) {
            $customer = new CustomerFactory()->createOne();
            $customer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

            $subscription = new SubscriptionFactory()
                ->for($product)
                ->for($customer)
                ->administrativeStatusActive()
                ->technicalStatusOk()
                ->forDomain($domain)
                ->createOne();
            $subscription->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());
        }

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'search' => 'migrated',
                        'orderBy' => ['updated_at_desc', 'domain_asc'],
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    #[Test]
    public function listSortsOnTheIdSharedWithTheJoinedProductsTable(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $firstCustomer = new CustomerFactory()->createOne();
        $firstCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $first = new SubscriptionFactory()
            ->for($product)
            ->for($firstCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $first->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $secondCustomer = new CustomerFactory()->createOne();
        $secondCustomer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $second = new SubscriptionFactory()
            ->for($product)
            ->for($secondCustomer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $second->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'search' => 'migrated',
                        'orderBy' => ['id_desc'],
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    #[Test]
    public function listKeepsTheDomainColumnWhenTheColumnPickerAsksForIt(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $customer = new CustomerFactory()->createOne();
        $customer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-hosting.nl')
            ->createOne();
        $subscription->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.migration-state.list') . '?'
                    . http_build_query([
                        'fields' => [
                            'domain',
                            'business_unit',
                            'batch_group',
                            'product',
                            'provider',
                            'technical_status',
                        ],
                        'orderBy' => ['id_desc'],
                    ]),
            )
            ->assertOk()
            ->assertJsonPath('data.0.domain', 'migrated-hosting.nl')
            ->assertJsonPath('data.0.technical_status', TechnicalStatus::OK->value);
    }

    #[Test]
    public function showReturnsTheMigrationStateOfASubscriptionThatCameOutOfAMigration(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $customer = new CustomerFactory()->createOne();
        $customer->migratedCustomers()->attach(new MigratedCustomersFactory()->createOne());

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for($customer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('migrated-extension.nl')
            ->createOne();
        $subscription->migratedSubscriptions()->attach(new MigratedSubscriptionsFactory()->createOne());

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.migration-state.show', ['subscription' => $subscription->uuid]))
            ->assertOk()
            ->assertJsonPath('uuid', $subscription->uuid)
            ->assertJsonPath('domain', 'migrated-extension.nl');
    }

    #[Test]
    public function showIsNotFoundForASubscriptionThatDidNotComeOutOfAMigration(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne([
            'name' => 'nl-domeinnaam',
            'slug' => 'extension_nl',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for(new CustomerFactory()->createOne())
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain('not-migrated.nl')
            ->createOne();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.migration-state.show', ['subscription' => $subscription->uuid]))
            ->assertNotFound();
    }
}
