<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Jobs;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Redirects\RedirectTechnicalPayload;
use Waterfront\Domain\Ferry\Jobs\TechnicalRedirectMigrationJob;
use Waterfront\Domain\Ferry\Services\AdfPayloadService;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(TechnicalRedirectMigrationJob::class)]
class TechnicalRedirectMigrationJobTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.nl';
    private const string LEGACY_SERVER_HOSTNAME = 'legacy-server.nl';

    private Subscription $redirectSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2023-12-11 12:00:00');

        $customer = CustomerFactory::new()->createOne();

        $hostingProductGroup = ProductGroupFactory::new()
            ->hosting()
            ->createOne();

        $redirectProduct = ProductFactory::new()
            ->for($hostingProductGroup)
            ->createOne([
                'name' => ProductType::FREE_REDIRECT->value,
                'slug' => ProductType::FREE_REDIRECT->value,
            ]);

        $this->redirectSubscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($redirectProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($customer);

        $this->redirectSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $this->redirectSubscription->save();

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => self::LEGACY_SERVER_HOSTNAME,
        ]);
    }

    /**
     * @param array<int, Response> $pdnsPayload
     */
    #[DataProvider('redirectMigrationJobProvider')]
    #[Test]
    public function redirectMigrationJob(array $pdnsPayload, bool $redirectAlreadyExists, string $source): void
    {
        Http::fake();

        $pdnsMock = $this->makePdnsWithMultipleResponses($pdnsPayload);

        $this->pdns($pdnsMock);

        $redirectService = self::createMock(RedirectService::class);
        if ($redirectAlreadyExists) {
            $redirects = [
                [
                    'source' => $source,
                ],
            ];

            $redirectService->expects(self::once())
                ->method('listRedirects')
                ->willReturn($redirects);
        } else {
            $redirectService->expects(self::once())
                ->method('listRedirects')
                ->willReturn([]);

            $redirectService->expects(self::once())
                ->method('createRedirect')
                ->willReturn(
                    new RedirectResult(
                        provisionData: self::createStub(CreateRedirectRequest::class),
                        provisionStatus: ProvisionStatus::SUCCESS
                    )
                );
        }

        $this->app->bind(
            RedirectService::class,
            fn (): RedirectService => $redirectService
        );

        $payload = new RedirectTechnicalPayload(
            source: $source,
            destination: 'test.com',
            type: RedirectType::TEMPORARY->value
        );

        $job = new TechnicalRedirectMigrationJob(
            $this->redirectSubscription->refresh(),
            TechnicalStatus::ERROR->value,
            $payload
        );

        $adfService = self::resolve(AdfPayloadService::class);
        $dispatcher = self::resolve(Dispatcher::class);
        $logger = self::resolve(LoggerInterface::class);

        $job->handle($adfService, $dispatcher, $logger);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function redirectMigrationJobProvider(): iterable
    {
        yield 'Zone not found when creating redirect' => [
            'pdnsPayload' => [
                new Response(
                    404,
                    [],
                ),
            ],
            'redirectAlreadyExists' => false,
            'source' => 'bla.com',
        ];

        yield 'Redirect already exists in redirecting database' => [
            'pdnsPayload' => [
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody(self::TEST_DOMAIN)
                ),
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody(self::TEST_DOMAIN)
                ),
                new Response(
                    200,
                    [],
                    self::getStaticMockedZoneResponseBody(self::TEST_DOMAIN)
                ),
            ],
            'redirectAlreadyExists' => true,
            'source' => 'redirectsource.com',
        ];
    }
}
