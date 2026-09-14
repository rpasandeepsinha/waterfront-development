<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\RealtimeRegister;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Throwable;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Ferry\Dto\Domains\DomainMigrationPayload;
use Waterfront\Domain\Ferry\Exceptions\DomainBusinessUnitNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\DomainContactHandleException;
use Waterfront\Domain\Ferry\Exceptions\RemoteDomainForbiddenException;
use Waterfront\Domain\Ferry\Jobs\TechnicalDomainMigrationJob;
use Waterfront\Domain\Ferry\Repositories\MigratableSubscriptionRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(TechnicalDomainMigrationJob::class)]
class TechnicalDomainMigrationJobTest extends IntegrationTestCase
{
    #[Test]
    public function rollback(): void
    {
        Http::fake();

        $incomingOutgoingResponse = json_encode(
            include __DIR__ . '/data/domain_details_valid.php',
            JSON_THROW_ON_ERROR,
        );
        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: $incomingOutgoingResponse,
            ),
            // trigger an error.
            new Response(
                status: 500,
                body: $contactResponse,
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);
        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $domain = 'example.nl';
        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $subscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->createOne([
            'domain' => $domain,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);
        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $migratedCustomer->migratedSubscriptions()->attach($migrationSubscription);
        $migratedCustomer->customers()->attach($customer);
        $deployment = DomainDeploymentFactory::new()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $domainProvider->id,
        ]);

        $subscriptions = self::resolve(MigratableSubscriptionRepository::class)
            ->getSubscriptionsForDomainContactMigration($customer);

        foreach ($subscriptions as $item) {
            self::assertSame(ProviderSlug::PLACEHOLDER, $item->domainDeployment?->provider->slug);
            self::assertNull($item->domainDeployment->contactOwner);
        }

        $referenceSubscriptionId = $migrationSubscription->reference_subscription_id;

        self::assertIsString($referenceSubscriptionId);

        $technicalDomainPayload = new DomainMigrationPayload(
            null,
            $referenceSubscriptionId,
            ProviderSlug::REALTIME_REGISTER,
        );

        $job = new TechnicalDomainMigrationJob($subscription, $subscription->technical_status, $technicalDomainPayload);
        $dispatcher = self::resolve(Dispatcher::class);

        try {
            $dispatcher->dispatch($job);
            self::fail('This point should not be reached');
        } catch (DomainContactHandleException) {
            $deployment->refresh();

            self::assertSame(ProviderSlug::PLACEHOLDER, $deployment->provider->slug);
            $owner = $deployment->contactOwner;
            self::assertNull($owner);
        }
    }

    /**
     * @param class-string $expectedExceptionClass
     */
    #[DataProvider('rollbackProvider')]
    #[Test]
    public function rollbackWithExceptions(
        string $expectedBusinessUnit,
        bool $exceptionOnAutorenewal,
        int $fetchDomainStatusCode,
        string $expectedExceptionClass,
    ): void {
        Http::fake();

        $domainDetails = include __DIR__ . '/data/domain_details_valid.php';

        if ($exceptionOnAutorenewal) {
            $domainDetails['autoRenew'] = false;
        }

        $domainDetailsResponse = json_encode($domainDetails, JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: $fetchDomainStatusCode,
                body: $domainDetailsResponse,
            ),
            new Response(
                status: 200,
                body: json_encode(include __DIR__ . '/data/contact_valid_registrant.php', JSON_THROW_ON_ERROR),
            ),
            new Response(
                status: $exceptionOnAutorenewal ? 500 : 200,
                body: $exceptionOnAutorenewal ? 'error enabling autorenewal' : $domainDetailsResponse,
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);

        /**
         * This partial mock ensures that when the `RtrService` is resolved in the
         * DomainServiceFactory with the Argeweb business unit from this test we
         * still return the original RtrService instance with the mock SDK.
         *
         * @var RtrService&MockInterface $partialRtrMock
         */
        $partialRtrMock = $this->partialMock(RtrService::class, function (MockInterface $mock) use ($rtrService) {
            $mock->shouldReceive('setClient')->andReturn($rtrService);
        });

        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $partialRtrMock);

        $domain = 'example.nl';
        $businessUnit = 'argeweb';
        $customer = CustomerFactory::new()->createOne();
        $productGroupExtension = ProductGroupFactory::new()->extension()->createOne();
        $domainProvider = ProviderFactory::new()->domainPlaceholder()->createOne();
        ProviderFactory::new()->domainRtr()->createOne();
        $productExtension = ProductFactory::new()->for($productGroupExtension)->createOne(['slug' => 'extension_com']);
        $subscription = SubscriptionFactory::new()->for($productExtension)->for($customer)->createOne([
            'domain' => $domain,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => DomainStatus::ACTIVE->value,
        ]);
        $migrationSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $subscription->migratedSubscriptions()->attach($migrationSubscription);
        $subscription->save();
        $migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $migratedCustomer->migratedSubscriptions()->attach($migrationSubscription);
        $migratedCustomer->customers()->attach($customer);
        $existingBusinessUnit = DomainProviderBusinessUnitFactory::new()->state(['slug' => $businessUnit])->createOne();
        RtrProviderCredentialsFactory::new()->for($existingBusinessUnit)->createOne();
        $deployment = DomainDeploymentFactory::new()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $domainProvider->id,
            'domain_business_unit_id' => $existingBusinessUnit->id,
        ]);

        $subscriptions = self::resolve(MigratableSubscriptionRepository::class)
            ->getSubscriptionsForDomainContactMigration($customer);

        foreach ($subscriptions as $item) {
            self::assertSame(ProviderSlug::PLACEHOLDER, $item->domainDeployment?->provider->slug);
            self::assertNull($item->domainDeployment->contactOwner);
        }

        $referenceSubscriptionId = $migrationSubscription->reference_subscription_id;

        self::assertIsString($referenceSubscriptionId);

        $technicalDomainPayload = new DomainMigrationPayload(
            referenceDnsTemplateId: null,
            referenceSubscriptionId: $referenceSubscriptionId,
            driver: ProviderSlug::REALTIME_REGISTER,
            referenceDomainProviderBusinessUnitSlug: $expectedBusinessUnit,
        );

        $job = new TechnicalDomainMigrationJob($subscription, $subscription->technical_status, $technicalDomainPayload);
        $dispatcher = self::resolve(Dispatcher::class);

        try {
            $dispatcher->dispatch($job);
            self::fail('This point should not be reached');
        } catch (Throwable $exception) {
            $deployment->refresh();
            $owner = $deployment->contactOwner;

            self::assertSame(ProviderSlug::PLACEHOLDER, $deployment->provider->slug);
            self::assertNull($owner);
            self::assertNull($deployment->domain_business_unit_id);
            self::assertInstanceOf($expectedExceptionClass, $exception);
        }
    }

    /**
     * @return iterable<string, array{expectedBusinessUnit: string, exceptionOnAutorenewal: bool}>
     */
    public static function rollbackProvider(): iterable
    {
        yield 'wrong business unit' => [
            'expectedBusinessUnit' => 'does-not-exist',
            'exceptionOnAutorenewal' => false,
            'fetchDomainStatusCode' => HttpResponse::HTTP_OK,
            'expectedExceptionClass' => DomainBusinessUnitNotFoundException::class,
        ];

        yield 'exception on enabling autorenewal' => [
            'expectedBusinessUnit' => 'argeweb',
            'exceptionOnAutorenewal' => true,
            'fetchDomainStatusCode' => HttpResponse::HTTP_OK,
            'expectedExceptionClass' => EnableAutorenewalFailedException::class,
        ];

        yield 'forbidden on fetch domain' => [
            'expectedBusinessUnit' => 'argeweb',
            'exceptionOnAutorenewal' => false,
            'fetchDomainStatusCode' => HttpResponse::HTTP_FORBIDDEN,
            'expectedExceptionClass' => RemoteDomainForbiddenException::class,
        ];
    }
}
