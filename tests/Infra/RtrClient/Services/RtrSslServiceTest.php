<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use RealtimeRegister\Domain\Enum\StatusEnum;
use RealtimeRegister\Domain\Process as RtrProcess;
use RealtimeRegister\Domain\ProcessCollection;
use RealtimeRegister\RealtimeRegister;
use ReflectionClass;
use stdClass;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrSslService;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateRequester;

#[CoversClass(RtrSslService::class)]
class RtrSslServiceTest extends IntegrationTestCase
{
    private ProductSpec $productSpec;

    /** @var array<string, mixed> */
    private array $customerData;

    private Customer $customer;

    private Subscription $subscription;

    private Product $product;

    private Provider $sslProvider;

    private RtrSslService $rtrSslService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sslProvider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);

        $customer = new CustomerFactory()->withAddress([
            'street_name' => 'Street',
            'street_number' => 1,
            'city' => 'Vlissingen',
            'zip_code' => '1234XD',
        ])->createOne([
                    'organization' => 'Sandwave',
                    'department' => 'Sandwave department',
                    'coc_number' => '1234567890',
                    'phone_country_code' => '31',
                    'phone_area_code' => '40',
                    'phone_subscriber_number' => '1234567',
                    'first_name' => 'Eerste',
                    'last_name' => 'Laatste',
                    'email' => 'admin@local.testing',
                ]);

        $customer->refresh();
        $customer->load('address');
        $this->customer = $customer;

        $productGroup = ProductGroupFactory::new()->hosting()->createOne();
        $this->product = ProductFactory::new()->for($productGroup)->createOne();
        $productSpec = ProductSpecFactory::new()->for($this->product)->createOne([
            'name' => 'ssl.product_id',
            'value' => 'ssl_geotrust',
        ]);

        $subscription = SubscriptionFactory::new()->for($customer)->createOne([
            'product_uuid' => $this->product->uuid,
            'domain' => 'sandwave.io',
        ]);

        $this->customerData = $customer->toArray();
        $this->subscription = $subscription;
        $this->productSpec = $productSpec;

        $this->rtrSslService = self::resolve(RtrSslService::class);
    }

    #[DataProvider('certificateProvider')]
    #[Test]
    public function canCreateCertificate(string $domain, string $expectedDnsZone): void
    {
        $subscription = SubscriptionFactory::new()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'domain' => $domain,
            ]);

        $sslDeployment = new SslDeploymentFactory()
            ->for($subscription)
            ->for($this->sslProvider)
            ->createOne();

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(
                    201,
                    [
                        'Content-Type' => 'application/json',
                        'x-process-id' => '1',
                    ],
                    json_encode([
                        'commonName' => $domain,
                        'requiresAttention' => false,
                        'validations' => [
                            'dcv' => [],
                        ],
                    ], JSON_THROW_ON_ERROR)
                ),
            ],
            function (RequestInterface $request): void {
                $body = (object) json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);

                $approver = $body->approver;

                self::assertSame($this->getConfiguration()->getAsString('realtimeregisterclient.handles.billing'), $body->customer);
                self::assertSame('ssl_geotrust', $body->product);
                self::assertSame(12, $body->period);
                self::assertSame('Sandwave', $body->organization);
                self::assertSame('Sandwave department', $body->department);
                self::assertSame('Street 1', $body->address);
                self::assertSame('1234XD', $body->postalCode);
                self::assertSame('Vlissingen', $body->city);
                self::assertSame('1234567890', $body->coc);
                self::assertSame('sandwave.io', $body->dcv[0]->commonName);
                self::assertSame('DNS', $body->dcv[0]->type);
                self::assertSame('Eerste', $approver->firstName);
                self::assertSame('Laatste', $approver->lastName);
                self::assertSame('admin@local.testing', $approver->email);
                self::assertSame('+31.401234567', $approver->voice);
            }
        );

        $csrManagerMock = self::createMock(CsrManager::class);
        $csrManagerMock->expects(self::once())
            ->method('create')
            ->with($this->customerData, $domain);
        $csrManagerMock->expects(self::once())
            ->method('getRawCsr')
            ->with($domain)
            ->willReturn(include __DIR__ . '/../data/csr.php');

        $dnsMock = $this->mock(SslDnsService::class);
        $dnsMock->shouldReceive('updateDns')->once()->withArgs(
            static fn (Result $result, string $updateDnsDomain): bool =>
            $updateDnsDomain === $expectedDnsZone
            && Str::startsWith($result->getDnsRecord(), '_')
            && Str::endsWith($result->getDnsRecord(), '.' . $domain)
            && Str::contains($result->getDnsValue() ?? '', '.sectigo.com.')
        );

        $this->app->instance(RealtimeRegister::class, $sdk);
        $this->app->instance(SslDnsService::class, $dnsMock);
        $this->app->instance(CsrManager::class, $csrManagerMock);

        $sslService = self::resolve(RtrSslService::class);

        $result = $sslService->create($this->productSpec, 12, $this->customerData, $sslDeployment);

        $sslDeployment->refresh();

        self::assertSame(1, $result->getRequestId());
        self::assertSame(Result::STATUS_WAITING, $result->getStatus());

        self::assertSame(1, $sslDeployment->request_id);
        self::assertSame(json_encode([
            'process_id' => 1,
            'certificate_status' => 'Certificate request is created. Pending validation.',
        ]), $sslDeployment->last_result);
    }

    #[Test]
    public function canReissueCertificate(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => 12,
            'provider_id' => $this->sslProvider->id,
        ]);

        $csr = include __DIR__ . '/../data/csr.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(
                    201,
                    [
                        'Content-Type' => 'application/json',
                        'x-process-id' => '1',
                    ],
                    json_encode([
                        'commonName' => 'sandwave.io',
                        'requiresAttention' => false,
                        'validations' => [
                            'dcv' => [],
                        ],
                    ], JSON_THROW_ON_ERROR)
                ),
            ],
            function (RequestInterface $request) use ($csr): void {
                $body = (object) json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);
                $approver = $body->approver;

                self::assertSame('v2/ssl/certificates/12/reissue', $request->getUri()->getPath());
                self::assertSame($csr, $body->csr);
                self::assertSame('Sandwave', $body->organization);
                self::assertSame('Sandwave department', $body->department);
                self::assertSame('Street 1', $body->address);
                self::assertSame('1234XD', $body->postalCode);
                self::assertSame('Vlissingen', $body->city);
                self::assertSame('1234567890', $body->coc);
                self::assertSame('Eerste', $approver->firstName);
                self::assertSame('Laatste', $approver->lastName);
                self::assertSame('admin@local.testing', $approver->email);
                self::assertSame('+31.401234567', $approver->voice);
            }
        );
        $this->app->instance(RealtimeRegister::class, $sdk);
        $this->app->instance(SslDnsService::class, $this->getDnsMock());

        $sslService = self::resolve(RtrSslService::class);

        $result = $sslService->reissue($this->customerData, $sslDeployment, $csr);

        $sslDeployment->refresh();

        self::assertSame(1, $sslDeployment->request_id);
        self::assertSame(json_encode([
            'process_id' => 1,
            'certificate_status' => 'Certificate reissue has been requested. Pending validation.',
        ]), $sslDeployment->last_result);

        self::assertSame(1, $result->getRequestId());
        self::assertSame(Result::STATUS_ISSUED, $result->getStatus());
    }

    #[Test]
    public function throwsExceptionOnReissueCertificateWithoutCertificate(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => null,
            'provider_id' => $this->sslProvider->id,
        ]);

        $sslService = self::resolve(RtrSslService::class);

        self::expectException(LogicException::class);
        self::expectExceptionMessageIs('reissue certificate called, but no certificate set for SSL deployment with id:' . $sslDeployment->id);
        $sslService->reissue($this->customerData, $sslDeployment, 'csr');
    }

    #[Test]
    public function canRetrieveCertificate(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => 12,
            'provider_id' => $this->sslProvider->id,
        ]);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(201, [], (string) json_encode([
                    'id' => 12,
                    'process' => 1,
                    'status' => StatusEnum::STATUS_ACTIVE,
                    'certificateType' => 'SINGLE_DOMAIN',
                    'publicKeyAlgorithm' => 'RSA',
                    'organization' => 'Sandwave',
                    'department' => 'Sandwave department',
                    'address' => 'Street 1',
                    'domain' => 'test',
                    'product' => 'test',
                    'domainName' => 'test',
                    'validationType' => 'DOMAIN_VALIDATION',
                    'startDate' => 'now',
                    'expiryDate' => 'tomorrow',
                    'approver' => 'admin@local.testing',
                    'postalCode' => '1234XD',
                    'city' => 'Vlissingen',
                    'coc' => '1234567890',
                    'firstName' => 'Eerste',
                    'lastName' => 'Laatste',
                    'voice' => '+31.401234567',
                    'csr' => '',
                ])),
            ],
            function (RequestInterface $request): void {
                self::assertSame('v2/ssl/certificates/12', $request->getUri()->getPath());
            }
        );
        $this->instance(RealtimeRegister::class, $sdk);

        $sslService = self::resolve(RtrSslService::class);
        $result = $sslService->retrieve($sslDeployment);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame(StatusEnum::STATUS_ACTIVE, $result->getCertificateStatus());
    }

    #[Test]
    public function throwsExceptionOnRetrieveCertificateWithoutCertificateId(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => null,
            'provider_id' => $this->sslProvider->id,
        ]);

        self::expectException(LogicException::class);
        self::expectExceptionMessageIs("SSL deployment with id {$sslDeployment->id} has no certificate ID, so the SSL cannot be retrieved.");

        $sslService = self::resolve(RtrSslService::class);
        $sslService->retrieve($sslDeployment);
    }

    #[DataProvider('certificateProvider')]
    #[Test]
    public function canRenewCertificate(string $domain, string $expectedDnsZone): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'domain' => $domain,
            ]);

        $sslDeployment = new SslDeploymentFactory()
            ->for($subscription)
            ->for($this->sslProvider)
            ->createOne([
                'certificate_id' => 12,
            ]);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(
                    201,
                    [
                        'Content-Type' => 'application/json',
                        'x-process-id' => '1',
                    ],
                    json_encode([
                        'commonName' => $domain,
                        'requiresAttention' => false,
                        'validations' => [
                            'dcv' => [],
                        ],
                    ], JSON_THROW_ON_ERROR)
                ),
            ],
            function (RequestInterface $request) use ($domain): void {
                $body = json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);
                self::assertInstanceOf(stdClass::class, $body);

                $approver = $body->approver;

                $csrManager = self::resolve(CsrManager::class);
                $csr = $csrManager->getRawCsr($domain);

                self::assertSame('v2/ssl/certificates/12/renew', $request->getUri()->getPath());
                self::assertSame($csr, $body->csr);
                self::assertSame('Sandwave', $body->organization);
                self::assertSame('Sandwave department', $body->department);
                self::assertSame('Street 1', $body->address);
                self::assertSame('1234XD', $body->postalCode);
                self::assertSame('Vlissingen', $body->city);
                self::assertSame('1234567890', $body->coc);
                self::assertSame('Eerste', $approver->firstName);
                self::assertSame('Laatste', $approver->lastName);
                self::assertSame('admin@local.testing', $approver->email);
                self::assertSame('+31.401234567', $approver->voice);
            }
        );

        $csrManagerMock = self::createMock(CsrManager::class);
        $csrManagerMock->expects(self::once())
            ->method('create')
            ->with($this->customerData, $domain);
        $csrManagerMock->expects(self::exactly(2))
            ->method('getRawCsr')
            ->with($domain)
            ->willReturn(include __DIR__ . '/../data/csr.php');

        $dnsMock = $this->mock(SslDnsService::class);
        $dnsMock->shouldReceive('updateDns')->once()->withArgs(
            static fn (Result $result, string $updateDnsDomain): bool =>
            $updateDnsDomain === $expectedDnsZone
            && Str::startsWith($result->getDnsRecord(), '_')
            && Str::endsWith($result->getDnsRecord(), '.' . $domain)
            && Str::contains($result->getDnsValue() ?? '', '.sectigo.com.')
        );

        $this->app->instance(RealtimeRegister::class, $sdk);
        $this->app->instance(SslDnsService::class, $dnsMock);
        $this->app->instance(CsrManager::class, $csrManagerMock);

        $sslService = self::resolve(RtrSslService::class);

        $result = $sslService->renew($sslDeployment);

        $sslDeployment->refresh();

        self::assertSame(1, $sslDeployment->request_id);
        self::assertSame(json_encode([
            'process_id' => 1,
            'certificate_status' => 'Certificate renewal has been requested. Pending validation.',
        ]), $sslDeployment->last_result);

        self::assertSame(1, $result->getRequestId());
        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function throwsExceptionOnRenewCertificateWithoutCertificate(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => null,
            'provider_id' => $this->sslProvider->id,
        ]);

        $sslService = self::resolve(RtrSslService::class);

        self::expectException(LogicException::class);
        self::expectExceptionMessageIs('renew certificate called, but no certificate set for SSL deployment with id:' . $sslDeployment->id);
        $sslService->renew($sslDeployment);
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function certificateProvider(): iterable
    {
        yield 'Domain without subdomain' => [
            'domain' => 'ssl.test',
            'expectedDnsZone' => 'ssl.test',
        ];

        yield 'Domain with subdomain' => [
            'domain' => 'subdomain.ssl.test',
            'expectedDnsZone' => 'ssl.test',
        ];
    }

    #[Test]
    public function hasSslRequestReturnsTrueWhenProcessesExist(): void
    {
        $domain = 'example.nl'; // Matches identifier in json
        $sslListCertificatesData = include __DIR__ . '/../data/processes_list.php';

        $rtrSdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString([$sslListCertificatesData])
        );

        $service = $this->rtrSslService->setClient($rtrSdk);

        self::assertTrue($service->hasSslRequest($domain));
    }

    #[Test]
    public function hasSslRequestReturnsFalseWhenNoProcessesExist(): void
    {
        $rtrSdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString([])
        );

        $service = $this->rtrSslService->setClient($rtrSdk);

        self::assertFalse($service->hasSslRequest('nonexistent.nl'));
    }

    #[Test]
    public function getProcessesReturnsCollection(): void
    {
        $sslListCertificatesData = include __DIR__ . '/../data/processes_list.php';

        $rtrSdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString([$sslListCertificatesData])
        );

        $service = $this->rtrSslService->setClient($rtrSdk);
        $processes = $this->callPrivate($service, 'getProcesses', 'example.nl');

        self::assertNotNull($processes, 'Expected a ProcessCollection, got null');
        self::assertInstanceOf(ProcessCollection::class, $processes);

        $first = $processes->offsetGet(0);
        self::assertInstanceOf(RtrProcess::class, $first);
        self::assertSame($sslListCertificatesData['id'], $first->id);
    }

    #[Test]
    public function getProcessesReturnsNullWhenEmpty(): void
    {
        $rtrSdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString([]) // RTR returned no processes for this domain
        );

        $service = $this->rtrSslService->setClient($rtrSdk);
        $processes = $this->callPrivate($service, 'getProcesses', 'invalid.nl');

        self::assertNull($processes);
    }

    #[Test]
    public function getSslCnameRecord(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => 12,
            'provider_id' => $this->sslProvider->id,
        ]);

        $sslListCertificatesData = include __DIR__ . '/../data/processes_list.php';
        $processesInfoData = include __DIR__ . '/../data/processes_info.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString([$sslListCertificatesData])),
            new Response(200, [], $this->getJsonString($processesInfoData)),
        ]);

        $result = $this->rtrSslService->setClient($rtrSdk)->getSslCnameRecord($sslDeployment);
        self::assertNotNull($result);
        self::assertSame('CNAME', $result->dnsType);
        self::assertSame('_c7fbc2039e400c8ef74129ec7db1842c.example.nl.', $result->dnsRecord);
        self::assertSame('c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.', $result->dnsContent);
    }

    #[Test]
    public function getSslCnameRecordNoDcv(): void
    {
        $sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'certificate_id' => 12,
            'provider_id' => $this->sslProvider->id,
        ]);

        $sslListCertificatesData = include __DIR__ . '/../data/processes_list.php';
        $processesInfoDataNoDcv = include __DIR__ . '/../data/processes_info_no_dcv.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString([$sslListCertificatesData])),
            new Response(200, [], $this->getJsonString($processesInfoDataNoDcv)),
        ]);

        $result = $this->rtrSslService->setClient($rtrSdk)->getSslCnameRecord($sslDeployment);
        self::assertNull($result);
    }

    #[Test]
    public function resendDcvSuccessPersistsResult(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['domain' => 'example.nl']);

        $sslDeployment = SslDeploymentFactory::new()
            ->for($subscription)
            ->for($this->sslProvider)
            ->createOne();

        $processList = include __DIR__ . '/../data/processes_list.php';
        $rtrSdk = MockedClientFactory::makeSdk(200, $this->getJsonString([$processList]));
        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $csrManager = self::mock(CsrManager::class);
        $csrManager->shouldReceive('hasCsr')->once()->with('example.nl')->andReturnFalse()->ordered();
        $csrManager->shouldReceive('hasCsr')->once()->with('*.example.nl')->andReturnFalse()->ordered();

        $certRequester = self::mock(CertificateRequester::class);
        $certRequester->shouldReceive('resendDcv')
            ->once()
            ->withArgs(function (int $processId, string $commonName): bool {
                self::assertSame('example.nl', $commonName);
                self::assertGreaterThan(0, $processId);
                return true;
            })
            ->andReturn(Result::create([
                'status' => Result::STATUS_OK,
            ]));

        $rtrSslService = self::resolve(RtrSslService::class);

        $result = $rtrSslService->resendDcv($sslDeployment);

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        $sslDeployment->refresh();
        self::assertNotNull($sslDeployment->last_result_received);

        $persisted = (array) json_decode((string) $sslDeployment->last_result, true);
        self::assertSame('resendDcv', $persisted['action']);
        self::assertSame('example.nl', $persisted['commonName']);
        self::assertSame($processList['id'], $persisted['processId']);
        self::assertIsArray($persisted['result']);
        self::assertSame(Result::STATUS_OK, $persisted['result']['status']);
    }

    #[Test]
    public function resendDcvReturnsErrorWhenNoProcessId(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['domain' => 'noprocess.example']);

        $sslDeployment = SslDeploymentFactory::new()
            ->for($subscription)
            ->for($this->sslProvider)
            ->createOne();

        $rtrSdk = MockedClientFactory::makeSdk(200, $this->getJsonString([]));
        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $certRequester = self::mock(CertificateRequester::class);
        $certRequester->shouldNotReceive('resendDcv');

        $rtrSslService = self::resolve(RtrSslService::class);

        $result = $rtrSslService->resendDcv($sslDeployment);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame(HttpResponse::HTTP_PRECONDITION_FAILED, $result->getErrorCode());
        self::assertSame(
            sprintf('No RTR certificate processes found for domain %s', 'noprocess.example'),
            $result->getErrorMessage()
        );
    }

    #[Test]
    public function resendDcvFallsBackToDomainWhenCsrParseFails(): void
    {
        $subscription = SubscriptionFactory::new()
            ->for($this->product)
            ->for($this->customer)
            ->createOne(['domain' => 'fallback.example']);

        $sslDeployment = SslDeploymentFactory::new()
            ->for($subscription)
            ->for($this->sslProvider)
            ->createOne();

        $processList = include __DIR__ . '/../data/processes_list.php';
        $rtrSdk = MockedClientFactory::makeSdk(200, $this->getJsonString([$processList]));
        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $csrManager = self::createMock(CsrManager::class);
        $csrManager->expects(self::once())->method('hasCsr')->with('fallback.example')->willReturn(true);
        $csrManager->expects(self::once())->method('getRawCsr')->with('fallback.example')->willReturn(
            include __DIR__ . '/../data/csr.php'
        );
        $this->app->instance(CsrManager::class, $csrManager);

        $certRequester = self::mock(CertificateRequester::class);
        $certRequester->shouldReceive('getCommonNameFromCsr')->once()->andThrow(new InvalidArgumentException('bad csr'));
        $certRequester->shouldReceive('resendDcv')
            ->once()
            ->withArgs(function (int $processId, string $commonName): bool {
                self::assertSame('fallback.example', $commonName);
                return $processId > 0;
            })
            ->andReturn(Result::create(['status' => Result::STATUS_OK]));

        $rtrSslService = self::resolve(RtrSslService::class);

        $result = $rtrSslService->resendDcv($sslDeployment);

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        $sslDeployment->refresh();
        self::assertNotNull($sslDeployment->last_result_received);

        $persisted = (array) json_decode((string) $sslDeployment->last_result, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('resendDcv', $persisted['action']);
        self::assertSame('fallback.example', $persisted['commonName']);
    }

    private function callPrivate(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionClass($object);
        $m = $ref->getMethod($method);
        return $m->invoke($object, ...$args);
    }

    private function getDnsMock(): MockInterface
    {
        $dnsMock = $this->mock(SslDnsService::class);
        $dnsMock->shouldReceive('updateDns')->once()->withArgs(
            static fn (Result $result, string $domain): bool => $domain === 'sandwave.io'
            && Str::startsWith($result->getDnsRecord(), '_')
            && Str::endsWith($result->getDnsRecord(), '.' . $domain)
            && Str::contains($result->getDnsValue() ?? '', '.sectigo.com.')
        );
        return $dnsMock;
    }

    /**
     * json_encode returns string|false and we need string.
     *
     * @param array<string> $data
     */
    private function getJsonString(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
