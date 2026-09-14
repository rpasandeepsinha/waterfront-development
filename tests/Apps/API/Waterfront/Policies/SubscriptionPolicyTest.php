<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Policies;

use Generator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Rfc4122\UuidV4;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;

#[CoversClass(SubscriptionPolicy::class)]
#[AllowMockObjectsWithoutExpectations]
class SubscriptionPolicyTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test-domain.nl';

    #[DataProvider('dnsSubscriptionProvider')]
    #[Test]
    public function userCanManageDns(string $dnsSubscriptionStatus, bool $shouldBeAbleToManage): void
    {
        $customer = new CustomerFactory()->createOne();

        $mockAuthManager = $this->createStub(AuthenticationManager::class);
        $this->app->bind(AuthenticationManager::class, fn () => $mockAuthManager);

        $customerDto = new AuthenticatedCustomer(
            $customer,
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );
        $mockAuthManager->method('getAuthenticatedCustomer')->willReturn($customerDto);
        $mockAuthManager->method('getAuthenticatedSubject')->willReturn($customerDto);

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->createOne();

        // DNS child subscription
        new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->freeDns())
            ->forDomain(self::DOMAIN)
            ->for($domainSubscription, 'parent')
            ->state(['administrative_status' => $dnsSubscriptionStatus])
            ->createOne();

        $policy = self::resolve(SubscriptionPolicy::class);

        $shouldBeAbleToManage
            ? self::assertContains('manageDns', $policy->getAvailableActions($domainSubscription))
            : self::assertNotContains('manageDns', $policy->getAvailableActions($domainSubscription));
    }

    public static function dnsSubscriptionProvider(): Generator
    {
        yield 'Dns subscription with status active' => [AdministrativeStatus::ACTIVE->value, true];
        yield 'Dns subscription with status canceled' => [AdministrativeStatus::CANCELED->value, true];
        yield 'Dns subscription with status archived' => [AdministrativeStatus::ARCHIVED->value, false];
        yield 'Dns subscription with status archiving' => [AdministrativeStatus::ARCHIVING->value, true];
        yield 'Dns subscription with status expired' => [AdministrativeStatus::EXPIRED->value, false];
        yield 'Dns subscription with status inactive' => [AdministrativeStatus::INACTIVE->value, true];
        yield 'Dns subscription with status suspended' => [AdministrativeStatus::SUSPENDED->value, true];
    }

    #[Test]
    public function testSyncCertificateFromRtrIsAvailableForRtrSslSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();

        $mockAuthManager = $this->createStub(AuthenticationManager::class);
        $this->app->bind(AuthenticationManager::class, fn () => $mockAuthManager);

        $customerDto = new AuthenticatedCustomer(
            $customer,
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );
        $mockAuthManager->method('getAuthenticatedCustomer')->willReturn($customerDto);
        $mockAuthManager->method('getAuthenticatedSubject')->willReturn($customerDto);

        $sslSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->sslSingleDomain())
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne(['subscription_uuid' => $sslSubscription->uuid]);

        $policy = self::resolve(SubscriptionPolicy::class);

        self::assertContains('syncCertificateFromRtr', $policy->getAvailableActions($sslSubscription));
    }

    #[Test]
    public function testSyncCertificateFromRtrIsNotAvailableForNonRtrSslSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();

        $mockAuthManager = $this->createStub(AuthenticationManager::class);
        $this->app->bind(AuthenticationManager::class, fn () => $mockAuthManager);

        $customerDto = new AuthenticatedCustomer(
            $customer,
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );
        $mockAuthManager->method('getAuthenticatedCustomer')->willReturn($customerDto);
        $mockAuthManager->method('getAuthenticatedSubject')->willReturn($customerDto);

        $sslSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->sslSingleDomain())
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->openProviderProvider()
            ->createOne(['subscription_uuid' => $sslSubscription->uuid]);

        $policy = self::resolve(SubscriptionPolicy::class);

        self::assertNotContains('syncCertificateFromRtr', $policy->getAvailableActions($sslSubscription));
    }

    #[Test]
    public function testSyncCertificateFromRtrIsNotAvailableWhenNoSslDeploymentExists(): void
    {
        $customer = new CustomerFactory()->createOne();

        $mockAuthManager = $this->createStub(AuthenticationManager::class);
        $this->app->bind(AuthenticationManager::class, fn () => $mockAuthManager);

        $customerDto = new AuthenticatedCustomer(
            $customer,
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );
        $mockAuthManager->method('getAuthenticatedCustomer')->willReturn($customerDto);
        $mockAuthManager->method('getAuthenticatedSubject')->willReturn($customerDto);

        $sslSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->sslSingleDomain())
            ->createOne(['domain' => self::DOMAIN]);

        $policy = self::resolve(SubscriptionPolicy::class);

        self::assertNotContains('syncCertificateFromRtr', $policy->getAvailableActions($sslSubscription));
    }

    #[Test]
    public function testSyncCertificateFromRtrIsNotAvailableForNonSslSubscription(): void
    {
        $customer = new CustomerFactory()->createOne();

        $mockAuthManager = $this->createStub(AuthenticationManager::class);
        $this->app->bind(AuthenticationManager::class, fn () => $mockAuthManager);

        $customerDto = new AuthenticatedCustomer(
            $customer,
            new KratosIdentity(
                UuidV4::uuid4(),
                SchemaId::CUSTOMER,
                'active',
                null,
                new Traits('', null),
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
            ),
            true,
        );
        $mockAuthManager->method('getAuthenticatedCustomer')->willReturn($customerDto);
        $mockAuthManager->method('getAuthenticatedSubject')->willReturn($customerDto);

        $domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->createOne();

        $policy = self::resolve(SubscriptionPolicy::class);

        self::assertNotContains('syncCertificateFromRtr', $policy->getAvailableActions($domainSubscription));
    }
}
