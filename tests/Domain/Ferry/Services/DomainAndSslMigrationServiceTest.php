<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\DTO\RetrieveCustomerResponse;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Ferry\Dto\Domains\TechnicalMigrationDomain;
use Waterfront\Domain\Ferry\Exceptions\DomainBusinessUnitNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\NoCredentialsForDomainBusinessUnitException;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

#[CoversClass(DomainAndSslMigrationService::class)]
class DomainAndSslMigrationServiceTest extends IntegrationTestCase
{
    public DomainAndSslMigrationService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DomainAndSslMigrationService::class);
    }

    #[Test]
    public function attachBusinessUnitToDomainDeploymentWithPlaceholderDriver(): void
    {
        $testBuSlug = 'flexwebhosting';
        $driver = ProviderSlug::REALTIME_REGISTER;

        $businessUnit = DomainProviderBusinessUnitFactory::new()->state(['slug' => $testBuSlug])->createOne();

        RtrProviderCredentialsFactory::new()->for($businessUnit)->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->for(
                SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain()),
            )
            ->withPlaceholderProvider()
            ->createOne();

        $saved = $this->service->attachBusinessUnitToDomainDeployment(
            deployment: $domainDeployment,
            businessUnitSlug: $testBuSlug,
            providerSlug: $driver,
        );

        self::assertTrue($saved);

        $domainDeployment->refresh();

        self::assertNotNull($domainDeployment->businessUnit);
        self::assertTrue($domainDeployment->businessUnit->is($businessUnit));
    }

    #[Test]
    public function attachBusinessUnitToDomainDeployment(): void
    {
        $testBuSlug = 'flexwebhosting';

        $businessUnit = DomainProviderBusinessUnitFactory::new()->state(['slug' => $testBuSlug])->createOne();

        RtrProviderCredentialsFactory::new()->for($businessUnit)->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->for(
                SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain()),
            )
            ->withRtrProvider()
            ->createOne();

        $saved = $this->service->attachBusinessUnitToDomainDeployment(
            deployment: $domainDeployment,
            businessUnitSlug: $testBuSlug,
        );

        self::assertTrue($saved);

        $domainDeployment->refresh();

        self::assertNotNull($domainDeployment->businessUnit);
        self::assertTrue($domainDeployment->businessUnit->is($businessUnit));
    }

    #[Test]
    public function attachBusinessUnitToDomainDeploymentWithoutCredentials(): void
    {
        $testBuSlug = 'flexwebhosting';
        $businessUnit = DomainProviderBusinessUnitFactory::new()->state(['slug' => $testBuSlug])->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->for(
                SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain()),
            )
            ->withRtrProvider()
            ->createOne();

        self::expectException(NoCredentialsForDomainBusinessUnitException::class);
        self::expectExceptionMessageIs(
            sprintf(
                'The given business unit slug [%s] does not have credentials for the given provider [%s]. Please ensure that the business unit & credentials exists and is correctly configured.',
                $testBuSlug,
                $domainDeployment->provider->slug->value,
            ),
        );

        $saved = $this->service->attachBusinessUnitToDomainDeployment(
            deployment: $domainDeployment,
            businessUnitSlug: $testBuSlug,
        );

        self::assertTrue($saved);

        $domainDeployment->refresh();

        self::assertNotNull($domainDeployment->businessUnit);
        self::assertTrue($domainDeployment->businessUnit->is($businessUnit));
    }

    #[Test]
    public function attachBusinessUnitToDomainDeploymentThrowsExceptionWithoutBu(): void
    {
        $testBuSlug = 'flexwebhosting';
        $domainDeployment = DomainDeploymentFactory::new()
            ->for(
                SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain()),
            )
            ->withRtrProvider()
            ->createOne();

        self::expectException(DomainBusinessUnitNotFoundException::class);
        self::expectExceptionMessageIs(
            sprintf(
                'The given business unit slug [%s] could not be found. Please ensure that the business unit exists and is correctly configured.',
                $testBuSlug,
            ),
        );

        $this->service->attachBusinessUnitToDomainDeployment($domainDeployment, $testBuSlug);
    }

    #[Test]
    public function domainContactHandleWithBusinessUnit(): void
    {
        $testBuSlug = 'flexwebhosting';
        $domainDetails = include __DIR__ . '/data/domain_details_valid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);

        $businessUnit = DomainProviderBusinessUnitFactory::new()->state(['slug' => $testBuSlug])->createOne();

        RtrProviderCredentialsFactory::new()->for($businessUnit)->createOne();

        $migratedCustomer = MigratedCustomersFactory::new()->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $migratedSubscription->migratedCustomers()->save($migratedCustomer);

        $domainDeployment = DomainDeploymentFactory::new()
            ->for(
                SubscriptionFactory::new()->withCustomer()->for(ProductFactory::new()->nlDomain()),
            )
            ->withRtrProvider()
            ->for($businessUnit, 'businessUnit')
            ->createOne();

        $subscription = $domainDeployment->subscription;
        $subscription->migratedSubscriptions()->save($migratedSubscription);

        $technicalMigrationDomain = new TechnicalMigrationDomain($subscription, $domainDetails);

        $mockDomainService = self::mock(DomainService::class);

        $contactResponse = include __DIR__ . '/data/retrieve_customer_response_valid.php';

        $mockDomainService
            ->shouldReceive('retrieveContactHandle')
            ->with(
                $technicalMigrationDomain->domainDetails->registrant,
                ProviderSlug::REALTIME_REGISTER,
                self::assertCallbackIsModel($businessUnit),
            )
            ->andReturn($contactResponse);

        $this->app->bind(DomainService::class, fn () => $mockDomainService);
        $service = $this->app->make(DomainAndSslMigrationService::class);

        self::assertEmpty($subscription->customer->domainContacts);

        $service->createMigratedDomainContact(
            migratedCustomer: $migratedCustomer,
            customer: $subscription->customer,
            subscription: $subscription,
            domainProvider: $domainDeployment->provider,
            migrationDomain: $technicalMigrationDomain,
        );

        $domainDeployment->refresh();

        self::assertNotNull($domainDeployment->businessUnit);
        self::assertTrue($domainDeployment->businessUnit->is($businessUnit));

        $domainContacts = $domainDeployment->subscription->customer->domainContacts;
        self::assertNotEmpty($domainContacts);
        $domainContact = $domainContacts->first();
        self::assertInstanceOf(DomainContact::class, $domainContact);

        self::assertSame('t.dummy@sandwave2.io', $domainContact->email);
        self::assertSame('John', $domainContact->first_name);
        self::assertSame('Doe', $domainContact->last_name);
        self::assertTrue(
            $domainContact
                ->providers()
                ->where('provider_id', $domainDeployment->provider_id)
                ->wherePivot('domain_business_unit_id', $businessUnit->id)
                ->exists(),
        );
    }

    #[Test]
    public function parseRemotePhoneForBuDefaultsForOpenProvider(): void
    {
        $service = $this->app->make(DomainAndSslMigrationService::class);

        $badNumbers = [
            '+333333331651116700567567567',
            '+3165111670099999999999',
            '016236666651116700',
            '+0000000000',
        ];

        foreach ($badNumbers as $badNumber) {
            $response = new RetrieveCustomerResponse(
                null,
                null,
                0,
                '',
                null,
                '',
                '',
                '',
                '',
                $badNumber,
                '',
                '',
                '',
                '',
                '',
                '',
            );

            $result = $service->parseRemotePhone($response);

            // Should all be configured to the default BU phone number
            self::assertSame(
                [
                    0 => '31',
                    1 => '123',
                    2 => '4567',
                ],
                $result,
            );
        }

        $goodNumber = '+31651116700';

        $response = new RetrieveCustomerResponse(
            null,
            null,
            0,
            '',
            null,
            '',
            '',
            '',
            '',
            $goodNumber,
            '',
            '',
            '',
            '',
            '',
            '',
        );

        $result = $service->parseRemotePhone($response);

        // Should all be configured to the default BU phone number
        self::assertSame(
            [
                0 => '31',
                1 => '6',
                2 => '51116700',
            ],
            $result,
        );
    }

    #[Test]
    public function parseRemotePhoneForParsableResponseForOpenProvider(): void
    {
        $service = $this->app->make(DomainAndSslMigrationService::class);

        $goodNumber = '+31651116700';

        $response = new RetrieveCustomerResponse(
            null,
            null,
            0,
            '',
            null,
            '',
            '',
            '',
            '',
            $goodNumber,
            '',
            '',
            '',
            '',
            '',
            '',
        );

        $result = $service->parseRemotePhone($response);

        self::assertSame(
            [
                0 => '31',
                1 => '6',
                2 => '51116700',
            ],
            $result,
        );
    }
}
