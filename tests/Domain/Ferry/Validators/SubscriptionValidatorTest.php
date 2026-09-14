<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Validators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Validators\SubscriptionMigrationValidator;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(SubscriptionMigrationValidator::class)]
class SubscriptionValidatorTest extends IntegrationTestCase
{
    private const string DUMMY_PRODUCT_SLUG = 'product-slug';

    private SubscriptionMigrationValidator $subscriptionMigrationValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $sitebuilderService = self::resolve(SitebuilderService::class);
        $this->subscriptionMigrationValidator = new SubscriptionMigrationValidator($sitebuilderService);
    }

    /**
     * @param array<string, string> $statuses
     */
    #[DataProvider('domainSubscriptionPropertiesProvider')]
    #[Test]
    public function validateEligibleForDomainMigration(
        ProductGroupType $productGroupType,
        ProviderSlug $domainProviderSlug,
        bool $domainSubscriptionExists,
        array $statuses,
        ?NotEligibleForMigrationException $expectedException,
    ): void {
        $productGroup = ProductGroupFactory::new()->createOne([
            'slug' => $productGroupType,
        ]);
        $product = ProductFactory::new()->for($productGroup)->createOne([
            'slug' => self::DUMMY_PRODUCT_SLUG,
        ]);

        $subscription = SubscriptionFactory::new()->withCustomer()->for($product)->createOne($statuses);

        if ($domainSubscriptionExists) {
            DomainDeploymentFactory::new()->createOne([
                'subscription_uuid' => $subscription->uuid,
                'provider_id' => ProviderFactory::new()->createOne([
                    'slug' => $domainProviderSlug,
                    'type' => ProviderType::DOMAIN,
                ]),
            ]);
        }

        if ($expectedException !== null) {
            self::expectExceptionObject($expectedException);
        } else {
            self::expectNotToPerformAssertions();
        }

        $this->subscriptionMigrationValidator->validateEligibleForDomainMigration($subscription);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function domainSubscriptionPropertiesProvider(): iterable
    {
        yield 'valid subscription' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => null,
        ];

        yield 'valid subscription CANCELLED' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::CANCELED->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => null,
        ];

        yield 'no domain subscription present' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => false,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::missingDomainSubscription(),
        ];

        yield 'wrong product group' => [
            'productGroupType' => ProductGroupType::SSL,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::incorrectProduct(self::DUMMY_PRODUCT_SLUG),
        ];

        yield 'wrong administrative status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::SUSPENDED->value),
        ];

        yield 'wrong technical status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::PLACEHOLDER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::DELETED->value,
            ],
            'expectedException' => NotEligibleForMigrationException::technicalStatusIncorrect(DomainStatus::DELETED->value),
        ];

        yield 'wrong domain provider' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainProviderSlug' => ProviderSlug::REALTIME_REGISTER,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::incorrectDomainProvider(ProviderSlug::REALTIME_REGISTER),
        ];
    }

    /**
     * @param array<string, string> $statuses
     */
    #[DataProvider('dnsSubscriptionPropertiesProvider')]
    #[Test]
    public function validateEligibleForDnsMigration(
        ProductGroupType $productGroupType,
        bool $domainSubscriptionExists,
        array $statuses,
        ?NotEligibleForMigrationException $expectedException,
    ): void {
        $productGroup = ProductGroupFactory::new()->createOne([
            'slug' => $productGroupType,
        ]);
        $product = ProductFactory::new()->for($productGroup)->createOne([
            'slug' => self::DUMMY_PRODUCT_SLUG,
        ]);

        $subscription = SubscriptionFactory::new()->withCustomer()->for($product)->createOne($statuses);

        if ($domainSubscriptionExists) {
            DomainDeploymentFactory::new()->withRtrProvider()->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);
        }

        if ($expectedException !== null) {
            self::expectExceptionObject($expectedException);
        } else {
            self::expectNotToPerformAssertions();
        }

        $this->subscriptionMigrationValidator->validateEligibleForDnsMigration($subscription);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function dnsSubscriptionPropertiesProvider(): iterable
    {
        yield 'domain product group: valid subscription' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => null,
        ];

        yield 'domain product group: missing domain subscription' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => false,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::missingDomainSubscription(),
        ];

        yield 'domain product group: wrong administrative status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::SUSPENDED->value),
        ];

        yield 'domain product group: wrong technical status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::DELETED->value,
            ],
            'expectedException' => NotEligibleForMigrationException::technicalStatusIncorrect(DomainStatus::DELETED->value),
        ];

        yield 'DNS product group: valid subscription' => [
            'productGroupType' => ProductGroupType::DNS,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::OK->value,
            ],
            'expectedException' => null,
        ];

        yield 'DNS product group: wrong administrative status' => [
            'productGroupType' => ProductGroupType::DNS,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                'technical_status' => TechnicalStatus::OK->value,
            ],
            'expectedException' => NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::SUSPENDED->value),
        ];

        yield 'DNS product group: wrong technical status' => [
            'productGroupType' => ProductGroupType::DNS,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => TechnicalStatus::DELETED->value,
            ],
            'expectedException' => NotEligibleForMigrationException::technicalStatusIncorrect(TechnicalStatus::DELETED->value),
        ];
    }

    /**
     * @param array<string, string> $statuses
     */
    #[DataProvider('nameserverSubscriptionPropertiesProvider')]
    #[Test]
    public function validateEligibleForNameserverMigration(
        ProductGroupType $productGroupType,
        bool $domainSubscriptionExists,
        array $statuses,
        ?NotEligibleForMigrationException $expectedException,
    ): void {
        $productGroup = ProductGroupFactory::new()->createOne([
            'slug' => $productGroupType,
        ]);
        $product = ProductFactory::new()->for($productGroup)->createOne([
            'slug' => self::DUMMY_PRODUCT_SLUG,
        ]);

        $subscription = SubscriptionFactory::new()->withCustomer()->for($product)->createOne($statuses);

        if ($domainSubscriptionExists) {
            DomainDeploymentFactory::new()->withRtrProvider()->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);
        }

        if ($expectedException !== null) {
            self::expectExceptionObject($expectedException);
        } else {
            self::expectNotToPerformAssertions();
        }

        $this->subscriptionMigrationValidator->validateEligibleForNameserverMigration($subscription);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function nameserverSubscriptionPropertiesProvider(): iterable
    {
        yield 'valid subscription' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => null,
        ];

        yield 'missing domain subscription' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => false,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::missingDomainSubscription(),
        ];

        yield 'wrong product group' => [
            'productGroupType' => ProductGroupType::SSL,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::incorrectProduct(self::DUMMY_PRODUCT_SLUG),
        ];

        yield 'wrong administrative status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::SUSPENDED->value,
                'technical_status' => DomainStatus::ACTIVE->value,
            ],
            'expectedException' => NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::SUSPENDED->value),
        ];

        yield 'wrong technical status' => [
            'productGroupType' => ProductGroupType::EXTENSION,
            'domainSubscriptionExists' => true,
            'statuses' => [
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'technical_status' => DomainStatus::DELETED->value,
            ],
            'expectedException' => NotEligibleForMigrationException::technicalStatusIncorrect(DomainStatus::DELETED->value),
        ];
    }
}
