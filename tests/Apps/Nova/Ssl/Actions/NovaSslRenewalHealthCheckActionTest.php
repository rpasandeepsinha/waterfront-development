<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Ssl\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\CertificateCollection;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Apps\Nova\Ssl\Actions\NovaSslRenewalHealthCheckAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(NovaSslRenewalHealthCheckAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaSslRenewalHealthCheckActionTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private Customer $customer;

    private Server $serverDirectAdmin;

    private Product $sslProduct;

    private Product $hostingProduct;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2024-05-03 12:34:56');

        $this->customer = CustomerFactory::new()->createOne();

        $this->hostingProduct = ProductFactory::new()
            ->for(ProductGroupFactory::new()->hosting()->createOne())
            ->createOne(['slug' => 'start']);

        $this->sslProduct = ProductFactory::new()
            ->for(ProductGroupFactory::new()->ssl()->createOne())
            ->createOne(['slug' => 'ssl_single_domain']);

        $this->serverDirectAdmin = ServerFactory::new()
            ->directadmin()
            ->createOne(['hostname' => 'my_hostname.nl']);

        $region = DnsRegionFactory::new()->createOne();
        DnsNameserverFactory::new()
            ->for($region)
            ->createOne(['nameserver' => 'nameserver01.testing.test']);
        DnsNameserverFactory::new()
            ->for($region)
            ->createOne(['nameserver' => 'nameserver02.testing.test']);
    }

    /**
     * @param array<mixed> $expectedResponse
     */
    #[DataProvider('validateSslDataProvider')]
    #[Test]
    public function handleActionOnRtrSslSubscription(
        bool $hostingSslEnabled,
        array $expectedResponse,
        ProviderSlug $sslProviderType,
        bool $hostingForDomainExists,
    ): void {
        $sslProvider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => $sslProviderType->value, 'enabled' => true, 'default' => true]);
        $hostingProvider = ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);

        // SSL subscription
        $baseSubscriptionSsl = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->sslProduct)
            ->createOne([
                'domain' => 'test-dns-intern-10.nl',
            ]);
        $sslDeployment  = SslDeploymentFactory::new()
            ->for($sslProvider, 'provider')
            ->for($baseSubscriptionSsl)
            ->createOne();

        if ($hostingForDomainExists) {
            // Hosting subscription
            $baseSubscriptionHosting = SubscriptionFactory::new()
                ->for($this->customer)
                ->for($this->hostingProduct)
                ->createOne([
                    'domain' => 'test-dns-intern-10.nl',
                ]);
            HostingDeploymentFactory::new()
                ->for($hostingProvider, 'provider')
                ->for($this->serverDirectAdmin)
                ->for($baseSubscriptionHosting)
                ->createOne([
                    'directadmin_customer_username' => 'directadmin_username',
                ]);

            $hostingUser = 'i_do_exist';

            $mockHostingService = self::createMock(HostingService::class);
            $mockHostingService->method('getPackageOnServerAsDto')
                ->willReturn(new DirectAdminUserPackage(
                    vdomains: '2',
                    nemails: '5',
                    mysql: '3',
                    bandwidth: '1024',
                    quota: '1024',
                    package: 'basic',
                ));
            $mockHostingService->method('getUserConfigAsDto')
                ->willReturnCallback(
                    fn (string $driver, string $userName, Server $server): SiteConfigInterface =>
                    match ([$driver, $userName, $server]) {
                        [ProviderSlug::DIRECTADMIN->value, $hostingUser, $this->serverDirectAdmin] => [],
                        default => throw new UnexpectedValueException(),
                    }
                )
                ->willReturn(new UserConfig(
                    dnscontrol: 'ON',
                    ssl: $hostingSslEnabled ? 'ON' : 'OFF',
                    loginKeys: 'ON',
                    vdomains: '10',
                    nemails: '10',
                    mysql: '10',
                    bandwidth: '1024',
                    quota: '1024',
                    package: 'basic',
                    usertype: HostingUserType::USER,
                    domain: 'testupgradefixversio.nl',
                ));

            $mockHostingService
                ->method('isUsingHostingServerAsNameserver')
                ->willReturn(true);

            $this->app->bind(HostingService::class, fn () => $mockHostingService);
        }

        $certificateCollection = CertificateCollection::fromArray([]);
        $rtrMigrationService = $this->createPartialMock(
            DomainAndSslMigrationService::class,
            ['listRtrSslCertificates']
        );
        $rtrMigrationService->method('listRtrSslCertificates')->willReturn($certificateCollection);
        $this->app->bind(DomainAndSslMigrationService::class, fn (): DomainAndSslMigrationService => $rtrMigrationService);

        $this->app->bind(DomainAndSslMigrationService::class, fn () => $rtrMigrationService);

        $action   = self::resolve(NovaSslRenewalHealthCheckAction::class);
        $response = $action->handle($this->getActionFields(), new Collection([$sslDeployment]));

        self::assertInstanceOf(ActionResponse::class, $response);
        $modal = $response['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        $data = (array) json_decode($modal->payload['code'], true);
        unset($data['validation_timeline']);
        self::assertSame($expectedResponse, $data);
    }

    /**
     * @return iterable<string, array{
     *     hostingSslEnabled: bool,
     *     expectedResponse: array<mixed>,
     *     sslProviderType: ProviderSlug,
     *     hostingForDomainExists: bool
     * }>
     */
    public static function validateSslDataProvider(): iterable
    {
        yield 'Fully correct ssl deployment' => [
            'hostingSslEnabled' => true,
            'expectedResponse' => include(__DIR__ . '/data/validate_ssl_success_response.php'),
            'sslProviderType' => ProviderSlug::REALTIME_REGISTER,
            'hostingForDomainExists' => true,
        ];

        yield 'SSL disabled on matching domain hosting site' => [
            'hostingSslEnabled' => false,
            'expectedResponse' => include(__DIR__ . '/data/validate_ssl_hosting_site_ssl_disabled.php'),
            'sslProviderType' => ProviderSlug::REALTIME_REGISTER,
            'hostingForDomainExists' => true,
        ];

        yield 'SSL deployment (partially migrated or Invoice only migration) in placeholder state' => [
            'hostingSslEnabled' => true,
            'expectedResponse' => include(__DIR__ . '/data/validate_ssl_placeholder_deployment_state.php'),
            'sslProviderType' => ProviderSlug::PLACEHOLDER,
            'hostingForDomainExists' => true,
        ];

        yield 'Standalone SSL certificate without a hosting site' => [
            'hostingSslEnabled' => true,
            'expectedResponse' => include(__DIR__ . '/data/validate_ssl_no_hosting_site.php'),
            'sslProviderType' => ProviderSlug::REALTIME_REGISTER,
            'hostingForDomainExists' => false,
        ];
    }

    private function getActionFields(): ActionFields
    {
        return new ActionFields(
            new Collection([]),
            new Collection([])
        );
    }
}
