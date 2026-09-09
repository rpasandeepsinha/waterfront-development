<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\SubscriptionPipe;

#[CoversClass(SubscriptionPipe::class)]
class SubscriptionPipeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2022-12-11 12:00:00');
    }

    #[Test]
    public function handleBadPayload(): void
    {
        $customer      = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_bad_payload.php');

        $validationReference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $validationReference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $subscriptionPipe = self::resolve(SubscriptionPipe::class);

        $validationPayload = $subscriptionPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($validationReference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::SUBSCRIPTION->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            'subscriptions.domain_extensions.0.slug' => [
                                'Product extension_nl not found for product group extension',
                            ],
                            'subscriptions.domain_extensions.1.slug' => [
                                'Product extension_nl not found for product group extension',
                            ],
                            'subscriptions.domain_extensions.2.slug' => [
                                'Product extension_nl not found for product group extension',
                            ],
                            'subscriptions.domain_extensions.1.domain' => [
                                'Dit veld heeft een dubbele waarde.',
                            ],
                            'subscriptions.domain_extensions.2.domain' => [
                                'Dit veld heeft een dubbele waarde.',
                            ],
                            'subscriptions.domain_extensions.1.next_contract_date' => [
                                'Dit veld dient een datum te zijn na 2021-01-01.',
                            ],
                            'subscriptions.domain_extensions.1.next_billing_date' => [
                                'Dit veld dient een datum te zijn na 2021-01-01.',
                            ],
                            'subscriptions.domain_extensions.1.cancel_date' => [
                                'Dit veld is geen geldige datum.',
                                'Dit veld dient een datum te zijn na 2017-01-01.',
                            ],
                            'subscriptions.hosting.0.slug' => [
                                'Product start not found for product group hosting',
                            ],
                            'subscriptions.hosting.0.labels.0' => [
                                'Dit veld komt niet voor in customer.labels.*.',
                            ],
                            'subscriptions.hosting.0.labels.1' => [
                                'Dit veld moet een string zijn.',
                                'Dit veld komt niet voor in customer.labels.*.',
                            ],
                            'subscriptions.ssl.0.slug' => [
                                'Product ssl_single_domain not found for product group ssl',
                            ],
                            'subscriptions.ssl.1.slug' => [
                                'Product ssl_single_domain not found for product group ssl',
                            ],
                            'subscriptions.ssl.0.domain' => [
                                'Dit veld is verplicht.',
                            ],
                            'subscriptions.ssl.1.domain' => [
                                'Dit veld is verplicht.',
                            ],
                            'subscriptions.dns.0.slug' => [
                                'Product dns_free not found for product group dns',
                            ],
                            'subscriptions.redirects.0.slug' => [
                                'Product free-redirect not found for product group redirect',
                            ],
                            'subscriptions.sitebuilder.0.slug' => [
                                'Product sitebuilder not found for product group hosting',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::SUBSCRIPTION_PIPE_PASSED->value,
                        'message' => 'subscription reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function handleAlreadyMigrated(): void
    {
        Log::spy();

        $customer      = include(__DIR__ . '/data/customer_correct_already_migrated.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct_already_migrated.php');

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->extension()->createOne())->createOne(['slug' => 'extension_nl']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $hostingGroup = ProductGroupFactory::new()->hosting()->createOne();

        $product = ProductFactory::new()->for($hostingGroup)->createOne(['slug' => 'start']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $product = ProductFactory::new()->freeRedirect()->createOne();
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->ssl()->createOne())->createOne(['slug' => 'ssl_single_domain']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $product = ProductFactory::new()->for(ProductGroupFactory::new()->dns()->createOne())->createOne(['slug' => 'dns_free']);
        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['price' => 1120]);

        $migratedCustomer = MigratedCustomersFactory::new()->createOne([
            'reference_customer_number' => 'identifierunique',
            'reference_name' => 'testmigration',
            'group_type' => 'testgroup',
        ]);

        $customerModel = CustomerFactory::new()->createOne(['email' => 't.dummy_unique@sandwave2.io']);
        $customerModel->migratedCustomers()->attach($migratedCustomer);

        $referenceProductId = 'Domeinnaam nl';
        $referenceSubscriptionId = '353580';

        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne([
            'reference_product_id' => $referenceProductId,
            'reference_subscription_id' => $referenceSubscriptionId,
        ]);

        $migratedCustomer->migratedSubscriptions()->attach($migrationSubscription);

        $validationReference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $validationReference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $validationMessage = sprintf(
            'Migration Subscription for reference Product ID: {%s} and reference Subscription ID: {%s} with migration reference: %s already exists',
            $referenceProductId,
            $referenceSubscriptionId,
            $validationReference
        );

        $matched = false;

        Log::shouldReceive('debug')->withArgs(function (string $args) use ($validationMessage, &$matched) {
            if ($args === $validationMessage) {
                $matched = true;
            }
            return true;
        });

        $subscriptionPipe = self::resolve(SubscriptionPipe::class);

        $validationPayload = $subscriptionPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertTrue($matched);

        self::assertSame($validationReference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::SUBSCRIPTION->value => [
                    [
                        'id' => MigrationValidation::SUBSCRIPTION_PIPE_PASSED->value,
                        'message' => 'subscription reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function badReferencePrice(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_bad_reference_price.php');

        $productSsl = ProductFactory::new()
            ->for(ProductGroupFactory::new()->ssl()->createOne())
            ->createOne(['slug' => 'ssl_single_domain']);

        new ProductPriceComponentFactory()
            ->for($productSsl)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 400,
            ]);

        $validationReference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $validationReference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $subscriptionPipe = self::resolve(SubscriptionPipe::class);

        $validationPayload = $subscriptionPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($validationReference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::SUBSCRIPTION->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            'subscriptions.ssl.0' => [
                                'Price is different for product ssl_single_domain and customer 0: new product price 400, reference product price 500',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::SUBSCRIPTION_PIPE_PASSED->value,
                        'message' => 'subscription reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }

    #[Test]
    public function missingVolumeDiscount(): void
    {
        $group = ProductGroupFactory::new()->volumeDiscount()->createOne();
        $discountProduct = ProductFactory::new()->for($group)->createOne([
            'slug' => 'volume_discount_brons',
        ]);

        new ProductPriceComponentFactory()->for($discountProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
        ]);

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_bad_volume_discount.php');

        $validationReference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $validationReference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $subscriptionPipe = self::resolve(SubscriptionPipe::class);

        $validationPayload = $subscriptionPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($validationReference, $validationPayload->validationReference);
        self::assertSame(
            [
                MigrationValidationPipes::SUBSCRIPTION->value => [
                    [
                        'id' => MigrationValidation::SUBSCRIPTION_VOLUME_DISCOUNT_PRODUCT_INCORRECT->value,
                        'message' => sprintf(
                            'Migration Subscription for reference Product ID: {employee} and reference Subscription ID: {353581} with migration reference: unique_reference_for_adf is a volume discount but the waterfront product with the ID: {%d} with the grouping Volume discounts did not have a discount attached to the product.',
                            $discountProduct->id,
                        ),
                    ],
                    [
                        'id' => MigrationValidation::SUBSCRIPTION_PIPE_PASSED->value,
                        'message' => 'subscription reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }
}
