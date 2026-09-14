<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use Carbon\CarbonImmutable;
use DateTime;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\Repository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Api\ContactsApi;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use RealtimeRegister\Support\AuthorizedClient;
use RealtimeRegister\Support\RealtimeRegisterResponse;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsExternalNameserverFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DnsVanityNameserverFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsExternalNameserver;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\DTO\HandleParameters;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Exceptions\DomainForbiddenException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Interfaces\HandleInterface;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKeySet;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\RtrClient\Action\ParseRtrTransferStatusToWfStatusAction;
use Waterfront\Infra\RtrClient\Exceptions\RtrApiException;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\RtrClient\Services\RtrIdnLanguageCodeResolver;
use Waterfront\Infra\RtrClient\Services\RtrResponseLogService;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(RtrService::class)]
class RtrServiceTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private RtrService $rtrService;

    private DomainDeployment $deployment;

    private DnsDeployment $dnsDeployment;

    private ProductGroup $domainProductGroup;

    private ProductGroup $dnsProductGroup;

    private DnsZone $masterExampleZone;

    public function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->domainProductGroup = new ProductGroupFactory()->createOne([
            'name' => 'Domein',
            'slug' => 'extension',
        ]);

        $this->dnsProductGroup = ProductGroupFactory::new()->createOne([
            'name' => 'DNS',
            'slug' => ProductGroupType::DNS,
        ]);

        $product = new ProductFactory()->for($this->domainProductGroup)->createOne();

        $dnsProduct = new ProductFactory()->for($this->dnsProductGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'example.nl',
                'product_uuid' => $product->uuid,
            ]);

        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->forDomain('example.nl')
            ->for($dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        $this->dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();

        $nameservers = new DnsNameserverFactory()->for(new DnsRegionFactory())->createMany(3);

        $this->dnsDeployment->dnsNameservers()->saveMany($nameservers);

        $domainContact = new DomainContactFactory()->createOne([
            'email' => 'domain@fake.nl',
            'first_name' => 'Dummy',
            'last_name' => 'tester',
            'customer_id' => $customer->id,
        ]);

        $this->deployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $this->rtrService = self::resolve(RtrService::class);

        $zoneMaster = new DnsZone(new Fqdn('example.com'), false);
        $zoneMaster->kind = PowerDnsZoneKind::MASTER->value;
        $this->masterExampleZone = $zoneMaster;
    }

    #[Test]
    public function listProcessesForDomain(): void
    {
        $domain = 'example.nl';

        $mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $response = json_encode([
            'entities' => [
                [
                    'id' => 12345,
                    'user' => 'api-user',
                    'customer' => 'customer-handle',
                    'status' => 'RUNNING',
                    'createdDate' => '2026-06-10T03:04:05Z',
                    'type' => 'domain',
                    'identifier' => $domain,
                    'action' => 'transfer',
                    'command' => ['domain:transfer'],
                ],
            ],
            'pagination' => [
                'limit' => 1,
                'offset' => 0,
                'total' => 1,
            ],
        ], JSON_THROW_ON_ERROR);

        $mockRtr
            ->expects(self::once())
            ->method('get')
            ->with('v2/processes', [
                'identifier' => $domain,
                'type' => 'domain',
            ])
            ->willReturn(new RealtimeRegisterResponse($response, [], 200));

        $result = self::resolve(RtrService::class)->listProcessesForDomain($domain);

        self::assertCount(1, $result);

        $process = $result[0];
        self::assertNotNull($process);
        self::assertSame($domain, $process->identifier);
        self::assertSame('domain', $process->type);
    }

    #[Test]
    public function hasZoneCheck(): void
    {
        $extension = 'de';
        $domain = sprintf('zonecheck.%s', $extension);

        $mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $response = (string) file_get_contents(__DIR__ . '/../data/tld_metadata_de.json');

        $mockRtr
            ->expects(self::once())
            ->method('get')
            ->with(sprintf('v2/tlds/%s/info', $extension))
            ->willReturn(new RealtimeRegisterResponse($response, [], 200));

        $rtrService = self::resolve(RtrService::class);

        self::assertTrue($rtrService->hasZoneCheck($domain));
    }

    #[Test]
    public function doesNotHaveZoneCheck(): void
    {
        $extension = 'nl';
        $domain = sprintf('zonecheck.%s', $extension);

        $mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $response = (string) file_get_contents(__DIR__ . '/../data/tld_metadata_nl.json');

        $mockRtr
            ->expects(self::once())
            ->method('get')
            ->with(sprintf('v2/tlds/%s/info', $extension))
            ->willReturn(new RealtimeRegisterResponse($response, [], 200));

        $rtrService = self::resolve(RtrService::class);

        self::assertFalse($rtrService->hasZoneCheck($domain));
    }

    #[Test]
    public function nameserverNotRequired(): void
    {
        $domain = 'nameservers-not-required.nl';

        $mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $response = include __DIR__ . '/../Live/data/tld_info_data_valid_nameservers_not_required.php';

        $mockRtr
            ->expects(self::once())
            ->method('get')
            ->with('v2/tlds/nl/info')
            ->willReturn(new RealtimeRegisterResponse(json_encode($response, JSON_THROW_ON_ERROR), [], 200));

        $rtrService = self::resolve(RtrService::class);

        self::assertFalse($rtrService->nameserversAreRequired($domain));
    }

    #[Test]
    public function nameserverRequired(): void
    {
        $domain = 'nameservers-required.nl';

        $mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $response = include __DIR__ . '/../Live/data/tld_info_data_valid.php';

        $mockRtr
            ->expects(self::once())
            ->method('get')
            ->with('v2/tlds/nl/info')
            ->willReturn(new RealtimeRegisterResponse(json_encode($response, JSON_THROW_ON_ERROR), [], 200));

        $rtrService = self::resolve(RtrService::class);

        self::assertTrue($rtrService->nameserversAreRequired($domain));
    }

    #[Test]
    public function contactValidationCategoriesForPrevalidationTld(): void
    {
        $tldInfoData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($tldInfoData),
            static function (RequestInterface $request): void {
                self::assertSame('GET', $request->getMethod());
                self::assertSame('v2/tlds/nl/info', $request->getUri()->getPath());
            },
        );

        $categories = $this->rtrService->setClient($sdk)->getContactValidationCategoriesForDomain('example.nl');

        self::assertSame(['General'], $categories);
    }

    #[Test]
    public function contactValidationCategoriesReturnedWhenPrevalidationIsNotRequired(): void
    {
        $tldInfoData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $tldInfoData['metadata']['creationRequiresPreValidation'] = false;

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($tldInfoData),
            static function (RequestInterface $request): void {
                self::assertSame('GET', $request->getMethod());
                self::assertSame('v2/tlds/nl/info', $request->getUri()->getPath());
            },
        );

        $categories = $this->rtrService->setClient($sdk)->getContactValidationCategoriesForDomain('example.nl');

        self::assertSame(['General'], $categories);
    }

    #[Test]
    public function contactValidationCategoriesEmptyWhenCategoryIsMissing(): void
    {
        $tldInfoData = include __DIR__ . '/../data/tld_info_data_valid.php';
        unset($tldInfoData['metadata']['validationCategory']);

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($tldInfoData),
            static function (RequestInterface $request): void {
                self::assertSame('GET', $request->getMethod());
                self::assertSame('v2/tlds/nl/info', $request->getUri()->getPath());
            },
        );

        $categories = $this->rtrService->setClient($sdk)->getContactValidationCategoriesForDomain('example.nl');

        self::assertSame([], $categories);
    }

    #[Test]
    public function validateContactHandle(): void
    {
        $sdk = MockedClientFactory::makeSdk(
            202,
            '',
            static function (RequestInterface $request): void {
                self::assertSame('POST', $request->getMethod());
                self::assertSame(
                    'v2/customers/sandwave-ote1/contacts/test-handle/validate',
                    $request->getUri()->getPath(),
                );

                $body = (array) json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

                self::assertSame(
                    ['categories' => ['General']],
                    $body,
                );
            },
        );

        $this->rtrService
            ->setHandle('sandwave-ote1')
            ->setClient($sdk)
            ->validateContactHandle('test-handle', ['General']);
    }

    #[Test]
    public function validateContactHandleRtrApiException(): void
    {
        $exception = new RuntimeException('RTR contact validation failed', 400);
        $rtrClient = self::createMock(AuthorizedClient::class);
        $rtrClient
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/customers/sandwave-ote1/contacts/test-handle/validate',
                ['categories' => ['General']],
            )
            ->willThrowException($exception);

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrClient);

        self::expectException(RtrApiException::class);
        self::expectExceptionMessageIs('RTR contact validation failed');

        $this->rtrService
            ->setHandle('sandwave-ote1')
            ->setClient($sdk)
            ->validateContactHandle('test-handle', ['General']);
    }

    #[Test]
    public function nameserversFlagIsTrueWhenInternalNameservers(): void
    {
        $this->dnsDeployment->update([
            'nameserver_type' => NameserverType::INTERNAL,
        ]);

        $regions = new DnsRegionFactory()->createMany(2);
        foreach ($regions as $i => $region) {
            $this->dnsDeployment
                ->dnsNameservers()
                ->save(
                    new DnsNameserverFactory()->createOne([
                        'dns_region_id' => $region->id,
                        'nameserver' => "int-ns{$i}.example.net",
                    ]),
                );
        }

        $data = include __DIR__ . '/../data/domain_details_valid.php';
        $sdk = MockedClientFactory::makeSdk(200, $this->getJsonString($data));

        $retrieveResult = $this->rtrService->setClient($sdk)->nameservers($this->deployment);

        self::assertTrue(
            $retrieveResult->getIsDefaultNameservers(),
            'When nameserver_type is INTERNAL, isDefaultNameservers should be true',
        );
    }

    #[Test]
    public function nameserversFlagIsFalseWhenExternalNameservers(): void
    {
        $this->dnsDeployment->update([
            'nameserver_type' => NameserverType::EXTERNAL,
        ]);

        /** @var DnsExternalNameserver[] $external */
        $external = DnsExternalNameserverFactory::new()->count(2)->make()->all();
        $this->dnsDeployment->externalNameservers()->saveMany($external);

        $data = include __DIR__ . '/../data/domain_details_valid.php';
        $sdk = MockedClientFactory::makeSdk(200, $this->getJsonString($data));

        $retrieve = $this->rtrService->setClient($sdk)->nameservers($this->deployment);

        self::assertFalse(
            $retrieve->getIsDefaultNameservers(),
            'When nameserver_type is EXTERNAL, isDefaultNameservers should be false',
        );
    }

    #[Test]
    public function nameserversFlagIsTrueWhenVanityNameservers(): void
    {
        $this->dnsDeployment->update([
            'nameserver_type' => NameserverType::VANITY,
        ]);

        /** @var DnsVanityNameserver[] $vanity */
        $vanity = DnsVanityNameserverFactory::new()->count(2)->make()->all();
        $this->dnsDeployment->vanityNameservers()->saveMany($vanity);

        $data = include __DIR__ . '/../data/domain_details_valid.php';
        $sdk = MockedClientFactory::makeSdk(200, $this->getJsonString($data));

        $retrieve = $this->rtrService->setClient($sdk)->nameservers($this->deployment);

        self::assertTrue(
            $retrieve->getIsDefaultNameservers(),
            'When nameserver_type is VANITY, isDefaultNameservers should be true',
        );
    }

    #[Test]
    public function check(): void
    {
        $data = include __DIR__ . '/../data/domain_availability_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $result = $this->rtrService->setClient($sdk)->check('mydomain.com');

        self::assertSame(CheckResult::STATUS_FREE, $result->getStatus());
    }

    #[DataProvider('tlds')]
    #[Test]
    public function isDnssecSupported(string $domain, string $unusedTld): void
    {
        $data = include __DIR__ . '/../data/tld_info_data_valid.php';

        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::once())->method('getDnsZone')->willReturn($this->masterExampleZone);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($data)),
        ]);
        $this->rtrService = new RtrService(
            $sdk,
            $dnsMock,
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $this->rtrService->isDnssecSupported($domain);
        self::assertTrue($result, 'Failed asserting dnssec is supported');
    }

    #[DataProvider('tlds')]
    #[Test]
    public function isDnssecSupportedNoDnsZone(string $domain, string $unusedTld): void
    {
        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::once())->method('getDnsZone')->willThrowException(new DnsZoneNotFoundException());

        $this->rtrService = new RtrService(
            self::resolve(RealtimeRegister::class),
            $dnsMock,
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $this->rtrService->isDnssecSupported($domain);
        self::assertFalse($result);
    }

    #[DataProvider('tlds')]
    #[Test]
    public function tldFromPossibleSld(string $domain, string $expected): void
    {
        $result = $this->rtrService->getTldFromPossibleSld($domain);

        self::assertSame($result, $expected);
    }

    /**
     * @return Generator<mixed>
     */
    public static function tlds(): Generator
    {
        yield [
            'example.nl',
            'nl',
        ];
        yield [
            'example.be',
            'be',
        ];
        yield [
            'example.co.uk',
            'uk',
        ];
        yield [
            'example.org.uk',
            'uk',
        ];
        yield [
            'example.lib.me.us',
            'us',
        ];
        yield [
            'example.com.vn',
            'vn',
        ];
        yield [
            'example.co.ug',
            'ug',
        ];
        yield [
            'example.co.us',
            'us',
        ];
        yield [
            'example.com.pl',
            'pl',
        ];
        yield [
            'some.weird.domain.extension.sld.tld',
            'tld',
        ];
    }

    #[Test]
    public function nameservers(): void
    {
        $data = include __DIR__ . '/../data/domain_details_valid.php';

        $nameserverArray = ['ns1.sandwave-test.com', 'ns02.sandwave-test.com', 'ns3.sandwave-test.com'];

        $regions = new DnsRegionFactory()->createMany(3);

        foreach ($regions as $key => $region) {
            $this->dnsDeployment
                ->dnsNameservers()
                ->save(
                    new DnsNameserverFactory()->createOne([
                        'dns_region_id' => $region->id,
                        'nameserver' => $nameserverArray[$key],
                    ]),
                );
        }

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $retrieveResult = $this->rtrService->setClient($sdk)->nameservers($this->deployment);

        $handles = $retrieveResult->getHandles();

        self::assertInstanceOf(HandleInterface::class, $handles);
        self::assertSame('johndoe', $handles->getOwnerHandle());
        self::assertSame('johndoe-admin', $handles->getAdminHandle());
        self::assertSame('johndoe-tech', $handles->getTechHandle());
        self::assertSame('johndoe', $handles->getBillingHandle());

        $nameservers = $retrieveResult->getNameServers();

        assert(is_array($nameservers));

        self::assertTrue($retrieveResult->getIsPrivateWhoisEnabled());
        self::assertSame($nameserverArray[0], $nameservers[0]['name']);
        self::assertNull($nameservers[0]['ip']);
        self::assertNull($nameservers[0]['ip6']);
        self::assertSame($nameserverArray[1], $nameservers[1]['name']);
        self::assertNull($nameservers[1]['ip']);
        self::assertNull($nameservers[1]['ip6']);
    }

    #[Test]
    public function retrieveCustomerHandle(): void
    {
        $data = include __DIR__ . '/../data/contact_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandle($handle);
        self::assertSame('sandwave-ote1', $result->getHandle());
        self::assertSame('Test', $result->getFirstName());
        self::assertSame('Account 1', $result->getLastName());
        self::assertSame('Testadres', $result->getStreet());
        self::assertSame('1', $result->getStreetNumber());
    }

    #[Test]
    public function retrieveCustomerHandleAddressParsing(): void
    {
        $data = include __DIR__ . '/../data/contact_valid.php';
        $data['addressLine'][0] = '2e Westerkade 22D drie hoog';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandle($handle);
        self::assertSame('2e Westerkade', $result->getStreet());
        self::assertSame('22D drie hoog', $result->getStreetNumber());
    }

    #[Test]
    public function retrieveCustomerHandleAddressNoNumber(): void
    {
        $data = include __DIR__ . '/../data/contact_valid.php';
        $data['addressLine'][0] = 'Terrace Place';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandle($handle);
        self::assertSame('Terrace Place', $result->getStreet());
        self::assertSame('', $result->getStreetNumber());
    }

    #[Test]
    public function retrieveCustomerHandleAddressEmpty(): void
    {
        $data = include __DIR__ . '/../data/contact_valid.php';
        $data['addressLine'][0] = '';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandle($handle);
        self::assertSame('', $result->getStreet());
        self::assertSame('', $result->getStreetNumber());
    }

    #[Test]
    public function retrieveCustomerHandleAddressNoLines(): void
    {
        $data = include __DIR__ . '/../data/contact_valid.php';
        $data['addressLine'] = [];

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($data),
        );

        $handle = 'sandwave-ote1';
        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandle($handle);
        self::assertSame('', $result->getStreet());
        self::assertSame('', $result->getStreetNumber());
    }

    #[Test]
    public function retrieveCustomerHandleForDomain(): void
    {
        $contactData = include __DIR__ . '/../data/contact_valid.php';
        $domainData = include __DIR__ . '/../data/domain_details_valid.php';
        $domain = $domainData['domainName'];
        $domainData['registrant'] = $contactData['handle'];

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(body: $this->getJsonString($domainData)),
            new Response(body: $this->getJsonString($contactData)),
        ]);

        $result = $this->rtrService->setClient($sdk)->retrieveCustomerHandleForDomain($domain);
        self::assertSame($contactData['handle'], $result->getHandle());
        self::assertSame('Test', $result->getFirstName());
        self::assertSame('Account 1', $result->getLastName());
    }

    #[Test]
    public function findOpenPrevalidationProcessForDomainReturnsNewestOpenDomainProcess(): void
    {
        $tldInfoData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $tldInfoData['metadata']['creationRequiresPreValidation'] = true;

        $processes = [
            [
                'id' => 1,
                'user' => 'test-user',
                'customer' => 'versiosandwave',
                'status' => ProcessStatusEnum::STATUS_COMPLETED,
                'createdDate' => '2026-05-20T10:00:00Z',
                'action' => 'create',
                'type' => 'domain',
                'identifier' => 'missing-domain.nl',
                'command' => [],
            ],
            [
                'id' => 2,
                'user' => 'test-user',
                'customer' => 'versiosandwave',
                'status' => ProcessStatusEnum::STATUS_RUNNING,
                'createdDate' => '2026-05-20T11:00:00Z',
                'action' => 'create',
                'type' => 'domain',
                'identifier' => 'missing-domain.nl',
                'command' => [],
            ],
            [
                'id' => 3,
                'user' => 'test-user',
                'customer' => 'versiosandwave',
                'status' => ProcessStatusEnum::STATUS_NEW,
                'createdDate' => '2026-05-20T12:00:00Z',
                'action' => 'create',
                'type' => 'domain',
                'identifier' => 'missing-domain.nl',
                'command' => [],
            ],
            [
                'id' => 4,
                'user' => 'test-user',
                'customer' => 'versiosandwave',
                'status' => ProcessStatusEnum::STATUS_RUNNING,
                'createdDate' => '2026-05-20T13:00:00Z',
                'action' => 'create',
                'type' => 'contact',
                'identifier' => 'missing-domain.nl',
                'command' => [],
            ],
        ];

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(body: $this->getJsonString($tldInfoData)),
            new Response(body: $this->getJsonString([
                'entities' => $processes,
                'pagination' => [
                    'total' => count($processes),
                    'offset' => 0,
                    'limit' => 10,
                ],
            ])),
        ]);

        $process = $this->rtrService->setClient($sdk)->findOpenPrevalidationProcessForDomain('missing-domain.nl');

        self::assertNotNull($process);
        self::assertSame(3, $process->id);
    }

    #[Test]
    public function retrieveRenewalDate(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';
        $domain = $domainDetailsValidData['domainName'];
        $actualDate = new DateTime($domainDetailsValidData['expiryDate']);

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $date = $this->rtrService->setClient($sdk)->retrieveRenewalDate($domain);
        self::assertTrue($date->eq(CarbonImmutable::instance($actualDate)));
    }

    #[Test]
    public function modifyHandle(): void
    {
        $domainDetailsData = include __DIR__ . '/../data/domain_details_valid.php';
        $contactValidData = include __DIR__ . '/../data/contact_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainDetailsData)),
            new Response(200, [], $this->getJsonString($contactValidData)),
        ]);

        $domain = 'example.nl';
        $handleData = [
            'handle' => 'test-ote1',
            'name' => null,
            'adressline' => null,
            'postalCode' => null,
            'city' => 'vlissingen',
            'country' => null,
            'email' => 'test@gmail.com',
            'voice' => '+31.1234567890',
            'brand' => 'sandwave',
            'organization' => 'Sandwave',
            'state' => null,
            'fax' => null,
        ];

        $result = $this->rtrService->setClient($sdk)->modifyHandle($domain, $handleData);

        self::assertTrue($result, 'failed modifying customer handle');
    }

    #[Test]
    public function minimalRegister(): void
    {
        $domain = 'example.nl';
        $handles = new Handles('test-handle');

        $rtrClient = self::createMock(AuthorizedClient::class);
        $mockLogger = self::createMock(LoggerInterface::class);
        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);

        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);
        $tldInfoValidData = json_encode(include __DIR__ . '/../data/tld_info_data_valid.php', JSON_THROW_ON_ERROR);
        $domainRegistrationData = json_encode(
            include __DIR__ . '/../data/domain_registration_valid.php',
            JSON_THROW_ON_ERROR,
        );

        $rtrClient
            ->expects(self::exactly(2))
            ->method('get')
            ->with(
                ...self::withConsecutive(
                    ['v2/customers/sandwave-ote1/contacts/test-handle'],
                    ['v2/tlds/nl/info'],
                ),
            )
            ->willReturnOnConsecutiveCalls(
                new RealtimeRegisterResponse($contactValidData, [], 200),
                new RealtimeRegisterResponse($tldInfoValidData, [], 200),
            );

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Register Using handles',
                [
                    LoggingContextKeys::META => [
                        'domain.handles' => [
                            [
                                'role' => 'ADMIN',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'BILLING',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'TECH',
                                'handle' => 'test-handle',
                            ],
                        ],
                    ],
                ],
            );

        $rtrClient
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/domains/example.nl',
                self::callback(
                    fn (array $body) => (
                        ! array_key_exists('ns', $body)
                        && ! array_key_exists('keyData', $body)
                        && $body['privacyProtect'] === false
                        && $body['contacts'][0]['handle'] === 'test-handle'
                    ),
                ),
            )
            ->willReturn(new RealtimeRegisterResponse($domainRegistrationData, [], 200));

        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => str_contains($json, '"domainName":"example.nl"'),
                ),
            );

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrClient);

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalRegister(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function minimalRegisterSendsLanguageCodeForIdnDomain(): void
    {
        $domain = 'xn--sportgemlde-s8a.com';
        $this->deployment->subscription->update(['domain' => $domain]);

        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid.php';
        $domainRegistrationData['domainName'] = $domain;

        $rtrRequests = [];
        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            responses: [
                new Response(
                    status: 200,
                    headers: [],
                    body: $this->getJsonString(include __DIR__ . '/../data/contact_valid.php'),
                ),
                new Response(
                    status: 200,
                    headers: [],
                    body: (string) file_get_contents(__DIR__
                    . '/../../../Apps/API/Waterfront/Orders/data/rtr-tld-metadata-com.json'),
                ),
                new Response(
                    status: 201,
                    headers: [],
                    body: $this->getJsonString($domainRegistrationData),
                ),
            ],
            assertClosure: static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->rtrService
            ->setHandle('sandwave-ote')
            ->setClient($sdk)
            ->minimalRegister(
                domainDeployment: $this->deployment,
                handles: new Handles('test-handle'),
            );

        self::assertCount(3, $rtrRequests);
        self::assertSame('v2/domains/' . $domain, $rtrRequests[2]->getUri()->getPath());

        $payload = json_decode((string) $rtrRequests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('languageCode', $payload);
        self::assertSame('GER', $payload['languageCode']);
    }

    /**
     * @param array<string, mixed> $domainRegistrationData
     */
    #[DataProvider('registrationResultStatusDataProvider')]
    #[Test]
    public function minimalRegisterMapsRegistrationResultStatus(
        array $domainRegistrationData,
        DomainStatus $expectedStatus,
        int $responseStatus,
        ?RtrDomainStatus $expectedDomainStatus,
    ): void {
        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(body: $this->getJsonString(include __DIR__ . '/../data/contact_valid.php')),
            new Response(body: $this->getJsonString(include __DIR__ . '/../data/tld_info_data_valid.php')),
            new Response(status: $responseStatus, body: $this->getJsonString($domainRegistrationData)),
        ]);

        $result = $this->rtrService
            ->setHandle('sandwave-ote')
            ->setClient($sdk)
            ->minimalRegister(
                domainDeployment: $this->deployment,
                handles: new Handles('test-handle'),
            );

        self::assertSame($expectedStatus, $result->getStatus());
        self::assertSame($expectedDomainStatus, $this->deployment->refresh()->domain_status);
    }

    /**
     * @return Generator<string, array{array<string, mixed>, DomainStatus, int, RtrDomainStatus|null}>
     */
    public static function registrationResultStatusDataProvider(): Generator
    {
        yield 'accepted prevalidation response without status or expiry is pending validation' => [
            ['domainName' => 'example.nl'],
            DomainStatus::PENDING,
            202,
            RtrDomainStatus::PENDING_VALIDATION,
        ];

        yield 'pending validation status is pending' => [
            [
                'domainName' => 'example.nl',
                'status' => ['PENDING_VALIDATION'],
            ],
            DomainStatus::PENDING,
            202,
            RtrDomainStatus::PENDING_VALIDATION,
        ];

        yield 'pending validation wins over ok status' => [
            [
                'domainName' => 'example.nl',
                'status' => ['OK', 'PENDING_VALIDATION'],
            ],
            DomainStatus::PENDING,
            202,
            RtrDomainStatus::PENDING_VALIDATION,
        ];

        yield 'expiry date means active' => [
            [
                'domainName' => 'example.nl',
                'expiryDate' => '2026-05-19 01:02:03',
            ],
            DomainStatus::ACTIVE,
            200,
            RtrDomainStatus::OK,
        ];

        yield 'inactive status with expiry date means active' => [
            [
                'domainName' => 'example.nl',
                'expiryDate' => '2026-05-19 01:02:03',
                'status' => ['INACTIVE'],
            ],
            DomainStatus::ACTIVE,
            201,
            RtrDomainStatus::INACTIVE,
        ];

        yield 'failed only status is failed' => [
            [
                'domainName' => 'example.nl',
                'status' => ['SERVER_UPDATE_PROHIBITED'],
            ],
            DomainStatus::FAILED,
            200,
            RtrDomainStatus::SERVER_UPDATE_PROHIBITED,
        ];
    }

    #[Test]
    public function primaryDomainStatusPrioritizesPendingValidation(): void
    {
        $result = $this->rtrService->getPrimaryDomainStatusFromDomainStatusList([
            RtrDomainStatus::SERVER_UPDATE_PROHIBITED->value,
            RtrDomainStatus::OK->value,
            RtrDomainStatus::PENDING_VALIDATION->value,
        ]);

        self::assertSame(RtrDomainStatus::PENDING_VALIDATION, $result);
    }

    #[Test]
    public function minimalRegisterRtrException(): void
    {
        $domain = 'example.nl';
        $handles = new Handles('test-handle');
        $exception = new RealtimeRegisterClientException('Some error');

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Register Using handles',
                [
                    LoggingContextKeys::META => [
                        'domain.handles' => [
                            [
                                'role' => 'ADMIN',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'BILLING',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'TECH',
                                'handle' => 'test-handle',
                            ],
                        ],
                    ],
                ],
            );

        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);
        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => $json === json_encode($exception->getMessage()),
                ),
            );

        $rtrDomainsClientMock = self::createMock(AuthorizedClient::class);
        $rtrDomainsClientMock
            ->expects(self::once())
            ->method('get')
            ->with('v2/tlds/nl/info')
            ->willThrowException($exception);

        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);
        $rtrContactsClientMock = self::createMock(AuthorizedClient::class);
        $rtrContactsClientMock
            ->expects(self::once())
            ->method('get')
            ->with('v2/customers/sandwave-ote1/contacts/test-handle')
            ->willReturn(new RealtimeRegisterResponse($contactValidData, [], 200));

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrDomainsClientMock);
        $sdk->contacts = new ContactsApi($rtrContactsClientMock);

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalRegister(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(DomainStatus::FAILED, $result->getStatus());
    }

    #[Test]
    public function minimalRegisterUnknownException(): void
    {
        $domain = 'example.nl';
        $handles = new Handles('test-handle');
        $exception = new Exception('Some error');
        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Register Using handles',
                [
                    LoggingContextKeys::META => [
                        'domain.handles' => [
                            [
                                'role' => 'ADMIN',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'BILLING',
                                'handle' => 'test-handle',
                            ],
                            [
                                'role' => 'TECH',
                                'handle' => 'test-handle',
                            ],
                        ],
                    ],
                ],
            );

        $mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to register the domain {domain.name} with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);
        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => $json === json_encode($exception->getMessage()),
                ),
            );

        $rtrDomainsApiMock = self::createMock(AuthorizedClient::class);
        $rtrDomainsApiMock->expects(self::once())->method('get')->willThrowException($exception);

        $rtrContactsClientMock = self::createStub(AuthorizedClient::class);
        $rtrContactsClientMock->method('get')->willReturn(new RealtimeRegisterResponse($contactValidData, [], 200));

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrDomainsApiMock);
        $sdk->contacts = new ContactsApi($rtrContactsClientMock);

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalRegister(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(DomainStatus::FAILED, $result->getStatus());
    }

    #[Test]
    public function register(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainRegistrationData)),
        ]);

        $period = 1;
        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->register(
            $this->deployment,
            $period,
            $customer,
            $handles,
            true,
            true,
        );

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function registerNoDnsZone(): void
    {
        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid_test_nl.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainRegistrationData)),
        ]);

        $period = 1;
        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $handles = new Handles('test-handle');

        $dnsService = self::createMock(DnsService::class);
        $dnsService->expects(self::once())->method('getDnsZone')->willThrowException(new DnsZoneNotFoundException());

        $this->rtrService->setDnsService($dnsService);
        $result = $this->rtrService->setClient($sdk)->register(
            $this->deployment,
            $period,
            $customer,
            $handles,
            true,
            true,
        );

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function registerSendsLanguageCodeForIdnDomain(): void
    {
        $domain = 'xn--sportgemlde-s8a.com';
        $this->deployment->subscription->update(['domain' => $domain]);

        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid.php';
        $domainRegistrationData['domainName'] = $domain;

        $rtrRequests = [];
        $sdk = MockedClientFactory::makeSdkWithMultipleReponses(
            responses: [
                new Response(
                    status: 200,
                    headers: [],
                    body: $this->getJsonString(include __DIR__ . '/../data/contact_valid.php'),
                ),
                new Response(
                    status: 200,
                    headers: [],
                    body: (string) file_get_contents(__DIR__
                    . '/../../../Apps/API/Waterfront/Orders/data/rtr-tld-metadata-com.json'),
                ),
                new Response(
                    status: 201,
                    headers: [],
                    body: $this->getJsonString($domainRegistrationData),
                ),
            ],
            assertClosure: static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $this->rtrService->setClient($sdk)->register(
            deployment: $this->deployment,
            period: 12,
            customer: new CustomerFactory()->makeOne(['customer_number' => 1]),
            handles: new Handles('test-handle'),
            isPrivateWhoisEnabled: true,
            dnssecEnabled: false,
        );

        self::assertCount(3, $rtrRequests);
        self::assertSame('v2/domains/' . $domain, $rtrRequests[2]->getUri()->getPath());

        $payload = json_decode((string) $rtrRequests[2]->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($payload);
        self::assertArrayHasKey('languageCode', $payload);
        self::assertSame('GER', $payload['languageCode']);
    }

    #[DataProvider('failedReasonDataProvider')]
    #[Test]
    public function registerFailedReason(string $expectedTranslation, string $rtrJsonError): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(400, [], $rtrJsonError),
        ]);

        $period = 1;
        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->register(
            $this->deployment,
            $period,
            $customer,
            $handles,
            true,
            true,
        );

        self::assertNotNull($result->getExceptionMessage());
        self::assertStringContainsString($rtrJsonError, $result->getExceptionMessage());
        self::assertSame(
            self::resolve(TranslatorInterface::class)->translate($expectedTranslation),
            $result->getReason(),
        );
    }

    #[Test]
    public function registerNotExistingHandle(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainRegistrationData)),
        ]);

        $period = 1;
        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->register(
            $this->deployment,
            $period,
            $customer,
            $handles,
            true,
            true,
        );

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function registerPremiumDomain(): void
    {
        $customer = new CustomerFactory()->createOne();

        // .nl does not have premium domains but this way we don't need to duplicate response data
        $product = new ProductFactory()->for($this->domainProductGroup)->createOne(
            [
                'name' => '.cars',
                'slug' => 'extension_premium_example_nl',
            ],
        );

        $dnsProduct = new ProductFactory()->for($this->dnsProductGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'example.nl',
                'product_uuid' => $product->uuid,
            ]);

        $domainContact = new DomainContactFactory()->createOne([
            'email' => 'domain@fake.nl',
            'first_name' => 'Dummy',
            'last_name' => 'tester',
            'customer_id' => $customer->id,
        ]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->forDomain('example.nl')
            ->for($dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        $dnsDeployment = new DnsDeploymentFactory()->for($dnsSubscription)->createOne();

        $nameservers = new DnsNameserverFactory()
            ->for(new DnsRegionFactory())
            ->createMany(3)
            ->each(function (DnsNameserver $dnsNameserver) use ($dnsDeployment) {
                $dnsDeployment->dnsNameservers()->save($dnsNameserver);
            });

        $dnsDeployment->refresh();

        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainRegistrationData = include __DIR__ . '/../data/domain_registration_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainRegistrationData)),
        ]);

        $period = 1;
        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->register(
            $domainDeployment,
            $period,
            $customer,
            $handles,
            true,
            true,
        );

        self::assertSame(DomainStatus::ACTIVE, $result->getStatus());
    }

    #[Test]
    public function transfer(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainTransferValidData = include __DIR__ . '/../data/domain_transfer_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainTransferValidData)),
        ]);

        $period = 1;
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->transfer(
            deployment: $this->deployment,
            period: $period,
            customer: [],
            handles: $handles,
            isPrivateWhoisEnabled: true,
            dnssecEnabled: true,
            transferSecret: 'transfer_code',
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result->getStatus());
    }

    #[Test]
    public function minimalTransfer(): void
    {
        $transferCode = 'my-transfer-code-1234';
        $domain = 'minimal-transfer.nl';

        $rtrClient = self::createMock(AuthorizedClient::class);

        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);
        $domainTransferValidData = json_encode(
            include __DIR__ . '/../data/domain_minimal_transfer_valid.php',
            JSON_THROW_ON_ERROR,
        );

        $rtrClient
            ->expects(self::once())
            ->method('get')
            ->with('v2/customers/sandwave-ote1/contacts/test-handle')
            ->willReturn(new RealtimeRegisterResponse($contactValidData, [], 200));

        $rtrClient
            ->expects(self::once())
            ->method('post')
            ->with(
                'v2/domains/minimal-transfer.nl/transfer',
                self::callback(
                    fn (array $body) => (
                        $body['authcode'] === $transferCode
                        && ! array_key_exists('ns', $body)
                        && ! array_key_exists('keyData', $body)
                        && ! array_key_exists('privacyProtect', $body)
                    ),
                ),
            )
            ->willReturn(new RealtimeRegisterResponse($domainTransferValidData, [], 200));

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrClient);

        $handles = new Handles('test-handle');
        $this->deployment->update(['transfer_secret' => $transferCode]);
        $this->deployment->subscription->update(['domain' => $domain]);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Transfer for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domains',
                ],
            );

        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => str_contains($json, '"domainName":"minimal-transfer.nl"'),
                ),
            );

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalTransfer(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result->getStatus());
    }

    #[Test]
    public function minimalTransferLogsResponseRtrException(): void
    {
        $transferCode = 'my-transfer-code-1234';
        $domain = 'minimal-transfer.nl';
        $exception = new RealtimeRegisterClientException('Some error message');

        $rtrClient = self::createMock(AuthorizedClient::class);

        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);

        $rtrClient
            ->expects(self::once())
            ->method('get')
            ->with('v2/customers/sandwave-ote1/contacts/test-handle')
            ->willReturn(new RealtimeRegisterResponse($contactValidData, [], 200));

        $rtrClient->expects(self::once())->method('post')->willThrowException($exception);

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrClient);

        $handles = new Handles('test-handle');
        $this->deployment->update(['transfer_secret' => $transferCode]);
        $this->deployment->subscription->update(['domain' => $domain]);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Transfer for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domains',
                ],
            );

        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => json_decode($json) === $exception->getMessage(),
                ),
            );

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalTransfer(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(TechnicalStatus::FAILED->value, $result->getStatus());
    }

    #[Test]
    public function minimalTransferLogsResponseUnknownException(): void
    {
        $transferCode = 'my-transfer-code-1234';
        $domain = 'minimal-transfer.nl';
        $exception = new Exception('Some error message');

        $rtrClient = self::createMock(AuthorizedClient::class);

        $contactValidData = json_encode(include __DIR__ . '/../data/contact_valid.php', JSON_THROW_ON_ERROR);

        $rtrClient
            ->expects(self::once())
            ->method('get')
            ->with('v2/customers/sandwave-ote1/contacts/test-handle')
            ->willReturn(new RealtimeRegisterResponse($contactValidData, [], 200));

        $rtrClient->expects(self::once())->method('post')->willThrowException($exception);

        $sdk = new RealtimeRegister('mock-key-rtr');
        $sdk->setClient($rtrClient);

        $handles = new Handles('test-handle');
        $this->deployment->update(['transfer_secret' => $transferCode]);
        $this->deployment->subscription->update(['domain' => $domain]);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockRtrResponseLog = self::createMock(RtrResponseLogService::class);

        $mockLogger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Minimal Transfer for domain {domain.name}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::PROVISIONING_TYPE => 'domains',
                ],
            );

        $mockLogger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to do minimal transfer for a domain [{domain.name}] with unknown error',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->deployment->subscription_uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $mockRtrResponseLog
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                self::callback(
                    fn (string $json) => json_decode($json) === $exception->getMessage(),
                ),
            );

        $configurationMock = self::createStub(ConfigurationInterface::class);
        $configurationMock->method('getAsString')->willReturn('sandwave-ote1');

        $rtrService = new RtrService(
            $sdk,
            self::resolve(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            $configurationMock,
            $mockRtrResponseLog,
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $mockLogger,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $result = $rtrService->setClient($sdk)->minimalTransfer(
            domainDeployment: $this->deployment,
            handles: $handles,
        );

        self::assertSame(TechnicalStatus::FAILED->value, $result->getStatus());
    }

    #[Test]
    public function transferPremium(): void
    {
        $customer = new CustomerFactory()->createOne();

        // .nl does not have premium domains but this way we don't need to duplicate response data
        $product = new ProductFactory()->for($this->domainProductGroup)->createOne(
            [
                'name' => '.cars',
                'slug' => 'extension_premium_example_nl',
            ],
        );

        $dnsProduct = new ProductFactory()->for($this->dnsProductGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'example.nl',
                'product_uuid' => $product->uuid,
            ]);

        $domainContact = new DomainContactFactory()->createOne([
            'email' => 'domain@fake.nl',
            'first_name' => 'Dummy',
            'last_name' => 'tester',
            'customer_id' => $customer->id,
        ]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->forDomain('example.nl')
            ->for($dnsProduct)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()
            ->for($dnsSubscription)
            ->withVanityNameserver()
            ->createOne();

        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';
        $domainTransferValidData = include __DIR__ . '/../data/domain_transfer_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(201, [], $this->getJsonString($domainTransferValidData)),
        ]);

        $period = 1;
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->transfer(
            deployment: $domainDeployment,
            period: $period,
            customer: [],
            handles: $handles,
            isPrivateWhoisEnabled: true,
            dnssecEnabled: true,
            transferSecret: 'transfer_code',
        );

        self::assertSame(TechnicalStatus::PENDING->value, $result->getStatus());
    }

    #[DataProvider('failedReasonDataProvider')]
    #[Test]
    public function transferFailedReason(string $expectedTranslation, string $rtrJsonError): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody('example.nl'),
            ),
            new Response(
                200,
                [],
                $this->getMockedKeyResponseBody(),
            ),
        ]);

        $this->pdns($pdns);

        $contactValidData = include __DIR__ . '/../data/contact_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($contactValidData)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(400, [], $rtrJsonError),
        ]);

        $period = 1;
        $handles = new Handles('test-handle');

        $this->rtrService->setDnsService(self::resolve(DnsService::class));
        $result = $this->rtrService->setClient($sdk)->transfer(
            deployment: $this->deployment,
            period: $period,
            customer: [],
            handles: $handles,
            isPrivateWhoisEnabled: true,
            dnssecEnabled: true,
            transferSecret: 'transfer_code',
        );

        self::assertNotNull($result->getExceptionMessage());
        self::assertNotNull($result->getReason());
        self::assertStringContainsString($rtrJsonError, $result->getExceptionMessage());
        self::assertSame(
            self::resolve(TranslatorInterface::class)->translate($expectedTranslation),
            $result->getReason(),
        );
    }

    public static function failedReasonDataProvider(): Generator
    {
        yield [
            'rtr-error.transfer-blocked',
            '{"message":"Transfer is not possible for a domain with statuses \'[CLIENT_TRANSFER_PROHIBITED, OK]\'", "type": "ValidationError"}',
        ];
        yield [
            'rtr-error.privacy-protect-not-supported',
            '{"message":"Privacy protect is not supported","type":"ValidationError"}',
        ];
        yield [
            'rtr-error.contact-info-missing',
            '{"message": "Contact requires extra information (registrant:1-S8qPt3SrqTGcXg6EgMjJR317FQlHKnce8Ng3R)", "type": "ValidationError"}',
        ];
        yield [
            'rtr-error.auth-code-invalid',
            '{"message": "The authorization code should be between 6 and 12 characters", "type": "ValidationError"}',
        ];
        yield [
            'rtr-error.auth-code-incorrect',
            '{"message":"Incorrect authorization code","type":"ValidationError"}',
        ];
        yield [
            'rtr-error.general',
            '{"message":"This is an error response from RTR that isnt known to us.","type":"ValidationError"}',
        ];
    }

    #[Test]
    public function modify(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $parameters = [
            'autoRenew' => true,
        ];
        $domain = 'example.nl';

        $result = $this->rtrService->setClient($sdk)->modify($domain, $parameters);

        self::assertTrue($result, 'Failed modifying domain');
    }

    #[Test]
    public function isDefaultNameservers(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $retrieveResult = $this->rtrService->setClient($sdk)->nameservers($this->deployment);

        self::assertTrue(
            $retrieveResult->getIsDefaultNameservers(),
            'Default nameserver should be set. Did you assign nameservers to the domain subscription?',
        );
    }

    #[Test]
    public function updateNameservers(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
            function (RequestInterface $request): void {
                $body = (array) json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                self::assertEqualsCanonicalizing(
                    [
                        'nameserverhostname1.nl',
                        'nameserverhostname2.nl',
                    ],
                    $body['ns'],
                );
            },
        );

        $domain = 'example.nl';

        $nameservers = [
            new Nameserver('nameserverhostname1.nl.', '127.0.0.1', '::1'),
            // period at end should be trimmed off
            new Nameserver('nameserverhostname2.nl', null, '::2'),
        ];

        $success = $this->rtrService->setClient($sdk)->updateNameServers($domain, $nameservers);

        self::assertTrue($success);
    }

    #[Test]
    public function retrieveDnssecKeys(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $domain = 'example.nl';
        $dnssecKeys = $this->rtrService->setClient($sdk)->retrieveDnssecKeys($domain);

        $expectedKeys = [
            [
                'alg' => '10',
                'flags' => '256',
                'protocol' => '3',
                'pubKey' => 'TFMwdFVsTkJMUzB0SUdGelpHWmhjMlJtWVhOa1ptRnpaR1poYzJSbQ==',
            ],
            [
                'alg' => '8',
                'flags' => '257',
                'protocol' => '3',
                'pubKey' => 'WmhjMlJtWVhOa1ptRnpaR1poYzJSbUxTMHRVbE5CTFMwdElHRnpaRw==',
            ],
        ];

        self::assertSame($expectedKeys, $dnssecKeys);
    }

    #[Test]
    public function enableDnssec(): void
    {
        $dnsRegion = new DnsRegionFactory()->createOne();
        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => 'ns1.sandwave-test.com',
        ]);
        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => 'ns02.sandwave-test.com',
        ]);

        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::exactly(2))->method('getDnsZone')->willReturn($this->masterExampleZone);
        $dnsMock->expects(self::once())->method('enableDnssec');
        $dnsMock
            ->expects(self::once())
            ->method('getDnsZoneKeys')
            ->willReturn(PowerDnsSecKeySet::fromArray([
                include __DIR__ . '/../../../../tests/Infra/OpenproviderClient/data/dnsseckey.php',
            ]));
        $this->app->bind(DnsService::class, static fn () => $dnsMock);

        $this->rtrService = self::resolve(RtrService::class);
        $domainRetrieveResult = include __DIR__ . '/../data/domain_details_valid.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
        ]);

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->enableDnssec($domain);

        self::assertTrue($result, 'Failed enabling DNSSEC');
    }

    #[Test]
    public function enableDnssecWithVanityNs(): void
    {
        $dnsRegion = new DnsRegionFactory()->createOne();
        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => 'ns1.sandwave-test.com',
        ]);
        new DnsNameserverFactory()->for($dnsRegion)->createOne([
            'nameserver' => 'ns02.sandwave-test.com',
        ]);

        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::exactly(2))->method('getDnsZone')->willReturn($this->masterExampleZone);
        $dnsMock->expects(self::once())->method('enableDnssec');
        $dnsMock
            ->expects(self::once())
            ->method('getDnsZoneKeys')
            ->willReturn(PowerDnsSecKeySet::fromArray([
                include __DIR__ . '/../../../../tests/Infra/OpenproviderClient/data/dnsseckey.php',
            ]));
        $this->app->bind(DnsService::class, static fn () => $dnsMock);

        $this->rtrService = self::resolve(RtrService::class);
        $domainRetrieveResult = include __DIR__ . '/../data/domain_details_valid_with_vanity_ns.php';
        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
        ]);

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->enableDnssec($domain);

        self::assertTrue($result, 'Failed enabling DNSSEC');
    }

    #[Test]
    public function enableDnssecFailureSlaveZone(): void
    {
        $zoneSlave = new DnsZone(new Fqdn('example.com'), false);
        $zoneSlave->kind = PowerDnsZoneKind::SLAVE->value;

        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::once())->method('getDnsZone')->willReturn($zoneSlave);
        $this->app->bind(DnsService::class, static fn () => $dnsMock);

        $this->rtrService = self::resolve(RtrService::class);
        $domainRetrieveResult = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainRetrieveResult)),
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->rtrService->setClient($sdk)->enableDnssec('example.nl');
    }

    #[Test]
    public function enableDnssecWithDnsSecKey(): void
    {
        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(self::once())->method('getDnsZone')->willReturn($this->masterExampleZone);
        $dnsMock->expects(self::never())->method('enableDnssec');
        $dnsMock->expects(self::never())->method('getDnsZoneKeys');

        $this->rtrService = new RtrService(
            self::resolve(RealtimeRegister::class),
            $dnsMock,
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $powerDnsSecKeySet = PowerDnsSecKeySet::fromArray([
            include __DIR__ . '/../../../../tests/Infra/OpenproviderClient/data/dnsseckey.php',
        ]);

        $tldInfoValidData = include __DIR__ . '/../data/tld_info_data_valid.php';

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString(include __DIR__ . '/../data/domain_details_valid.php')),
            new Response(200, [], $this->getJsonString($tldInfoValidData)),
            new Response(200, [], $this->getJsonString(include __DIR__ . '/../data/domain_details_valid.php')),
        ]);

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->enableDnssec($domain, $powerDnsSecKeySet->getKeys()[0]);

        self::assertTrue($result, 'Failed enabling DNSSEC');
    }

    #[Test]
    public function disableDnssec(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->disableDnssec($domain);

        self::assertTrue($result, 'Failed disabling DNSSEC');
    }

    #[Test]
    public function enablePrivateWhois(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->enablePrivateWhois($domain);

        self::assertTrue($result, 'failed disabling Private whois');
    }

    #[Test]
    public function disablePrivateWhois(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsValidData),
        );

        $domain = 'example.nl';
        $result = $this->rtrService->setClient($sdk)->disablePrivateWhois($domain);

        self::assertTrue($result, 'failed disabling Private whois');
    }

    #[Test]
    public function createHandle(): void
    {
        $contactValidData = include __DIR__ . '/../data/contact_valid.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($contactValidData),
        );

        $customer = new CustomerFactory()->makeOne(['customer_number' => 1]);
        $contact = new DomainContactFactory()->makeOne();
        $contact->setRelation('customer', $customer);

        $params = HandleParameters::createFromCustomerArray($contact->domainContactArray());

        $handle = $this->rtrService->setClient($sdk)->createContact($params);

        self::assertSame(40, strlen($handle), 'Contact handle did not come to a length of 40.');
        self::assertStringContainsString('1-', $handle, 'Handle does not contain customer number');
    }

    #[Test]
    public function suspendSuccessful(): void
    {
        $rtrRequests = [];

        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_status_ok.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainDetailsValidData)),
            new Response(200, []),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $this->rtrService->setClient($rtrSdk)->suspend('domain.nl');

        self::assertCount(2, $rtrRequests);
        self::assertSame(
            '{"status":["CLIENT_HOLD","OK"]}',
            $rtrRequests[1]->getBody()->getContents(),
        );
        self::assertSame(
            sprintf('v2/domains/%s/update', 'domain.nl'),
            $rtrRequests[1]->getUri()->getPath(),
        );
    }

    #[Test]
    public function suspendButDomainWasAlreadySuspended(): void
    {
        $this->expectNotToPerformAssertions();
        $domainDetailsData = include __DIR__ . '/../data/domain_details_status_suspended.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsData),
        );

        $this->rtrService->setClient($sdk)->suspend('domain.nl');
    }

    #[Test]
    public function suspendButDomainWasPendingDelete(): void
    {
        $domainDetailsData = include __DIR__ . '/../data/domain_details_status_pending_delete.php';

        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(
                'skipping suspending for {domain.name}, unable to update domain when pending_delete.',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'domain.nl',
                ],
            );

        $authorizedClientMock = self::createMock(AuthorizedClient::class);
        $authorizedClientMock->expects(self::never())->method('post');
        $authorizedClientMock
            ->expects(self::once())
            ->method('get')
            ->willReturn(new RealtimeRegisterResponse((string) json_encode($domainDetailsData), [], 200));

        $rtrService = new RealtimeRegister('monkeykey');
        $rtrService->setClient($authorizedClientMock);

        $rtrService = new RtrService(
            $rtrService,
            self::createStub(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $loggerMock,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );
        $rtrService->suspend('domain.nl');
    }

    #[Test]
    public function suspendFailed(): void
    {
        $domainDetailsValidData = include __DIR__ . '/../data/domain_details_status_ok.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainDetailsValidData)),
            new Response(400, [], ''),
        ]);

        $this->expectException(DomainModificationFailedException::class);
        $this->rtrService->setClient($rtrSdk)->suspend('domain.nl');
    }

    #[Test]
    public function suspendThrowsDomainForbiddenExceptionWhenRtrReturnsForbidden(): void
    {
        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed suspend domain {domain.name}, access forbidden by RTR.',
                self::callback(
                    static fn (array $context): bool => (
                        $context[LoggingContextKeys::DOMAIN_NAME] === 'domain.nl'
                        && $context[LoggingContextKeys::PROVISIONING_PROVIDER] === ProvisionProvider::RTR
                        && $context[LoggingContextKeys::PROVISIONING_TYPE] === ProvisionType::DOMAIN_NAME
                    ),
                ),
            );

        $rtrSdk = MockedClientFactory::makeSdk(403, '');

        $rtrService = new RtrService(
            $rtrSdk,
            self::createStub(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $loggerMock,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $this->expectException(DomainForbiddenException::class);
        $rtrService->suspend('domain.nl');
    }

    #[Test]
    public function unsuspendSuccessful(): void
    {
        $domainDetailsData = include __DIR__ . '/../data/domain_details_status_suspended.php';
        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainDetailsData)),
            new Response(200, []),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $this->rtrService->setClient($rtrSdk)->unsuspend('domain.nl');

        self::assertCount(2, $rtrRequests);
        self::assertSame(
            '{"status":["OK"]}',
            $rtrRequests[1]->getBody()->getContents(),
        );
        self::assertSame(
            sprintf('v2/domains/%s/update', 'domain.nl'),
            $rtrRequests[1]->getUri()->getPath(),
        );
    }

    #[Test]
    public function suspendButDomainWasAlreadyUnsuspended(): void
    {
        $this->expectNotToPerformAssertions();
        $domainDetailsData = include __DIR__ . '/../data/domain_details_status_unsuspended.php';

        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString($domainDetailsData),
        );

        $this->rtrService->setClient($sdk)->unsuspend('domain.nl');
    }

    #[Test]
    public function unsuspendFailed(): void
    {
        $domainDetailsData = include __DIR__ . '/../data/domain_details_status_suspended.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->getJsonString($domainDetailsData)),
            new Response(400, [], ''),
        ]);

        $this->expectException(DomainModificationFailedException::class);
        $this->rtrService->setClient($rtrSdk)->unsuspend('domain.nl');
    }

    #[Test]
    public function unsuspendThrowsDomainForbiddenExceptionWhenRtrReturnsForbidden(): void
    {
        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed unsuspend domain {domain.name}, access forbidden by RTR.',
                self::callback(
                    static fn (array $context): bool => (
                        $context[LoggingContextKeys::DOMAIN_NAME] === 'domain.nl'
                        && $context[LoggingContextKeys::PROVISIONING_PROVIDER] === ProvisionProvider::RTR
                        && $context[LoggingContextKeys::PROVISIONING_TYPE] === ProvisionType::DOMAIN_NAME
                    ),
                ),
            );

        $rtrSdk = MockedClientFactory::makeSdk(403, '');

        $rtrService = new RtrService(
            $rtrSdk,
            self::createStub(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $loggerMock,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $this->expectException(DomainForbiddenException::class);
        $rtrService->unsuspend('domain.nl');
    }

    #[Test]
    public function unsuspendThrowsDomainDoesNotExistExceptionWhenRtrReturnsNotFound(): void
    {
        $loggerMock = self::createMock(LoggerInterface::class);
        $loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed unsuspend domain {domain.name}, because it does not exist with RTR.',
                [
                    LoggingContextKeys::DOMAIN_NAME => 'domain.nl',
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                ],
            );

        $rtrSdk = MockedClientFactory::makeSdk(404, '');

        $rtrService = new RtrService(
            $rtrSdk,
            self::createStub(DnsService::class),
            self::resolve(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::resolve(PremiumDomainService::class),
            self::resolve(ConfigurationInterface::class),
            self::resolve(RtrResponseLogService::class),
            self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            self::resolve(PublicSuffixList::class),
            $loggerMock,
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $this->expectException(DomainDoesNotExistException::class);
        $rtrService->unsuspend('domain.nl');
    }

    #[Test]
    public function getTechnicalStatusFromDomainStatusList(): void
    {
        $exampleStatus = [
            'OK',
            'SERVER_RENEW_PROHIBITED',
        ];

        $result = $this->rtrService->getTechnicalStatusFromDomainStatusList($exampleStatus);

        self::assertSame(TechnicalStatus::OK->value, $result);
    }

    #[test]
    public function restoreDomainSuccessful(): void
    {
        $datetime = new DateTime();
        $sdk = MockedClientFactory::makeSdk(
            200,
            $this->getJsonString(
                [
                    'domain' => 'domain.nl',
                    'expiryDate' => $datetime->format(DateTimeFormat::DEFAULT),
                ],
            ),
        );
        $loggerMock = self::createMock(RtrResponseLogService::class);
        $loggerMock->expects(self::once())->method('logApiResponse');

        $rtrService = new RtrService(
            $sdk,
            self::createStub(DnsService::class),
            self::createStub(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::createStub(PremiumDomainService::class),
            self::createStub(ConfigurationInterface::class),
            $loggerMock,
            self::createStub(ParseRtrTransferStatusToWfStatusAction::class),
            self::createStub(PublicSuffixList::class),
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );

        $rtrService->restore('domain.nl');
    }

    #[test]
    public function restoreDomainthrowsException(): void
    {
        $sdk = MockedClientFactory::makeSdk(
            400,
            $this->getJsonString(
                [
                    'type' => 'ProcessError',
                    'message' => 'An unrecoverable error was encountered during the execution of your request',
                ],
            ),
        );
        $loggerMock = self::createMock(RtrResponseLogService::class);
        $loggerMock
            ->expects(self::once())
            ->method('logApiResponse')
            ->with(
                'Bad Request: {"type":"ProcessError","message":"An unrecoverable error was encountered during the execution of your request"}',
            );

        $rtrService = new RtrService(
            $sdk,
            self::createStub(DnsService::class),
            self::createStub(DnsNameserverAssigner::class),
            self::resolve(RtrErrorParseService::class),
            self::createStub(PremiumDomainService::class),
            self::createStub(ConfigurationInterface::class),
            $loggerMock,
            self::createStub(ParseRtrTransferStatusToWfStatusAction::class),
            self::createStub(PublicSuffixList::class),
            self::createStub(LoggerInterface::class),
            self::createStub(DnsDeploymentRepository::class),
            self::resolve(Repository::class),
            self::resolve(RtrIdnLanguageCodeResolver::class),
        );
        $this->expectException(RealtimeRegisterClientException::class);

        $rtrService->restore('domain.nl');
    }

    /**
     * json_encode returns string|false and we need string.
     *
     * @param array<mixed> $data
     *
     * @throws RuntimeException
     */
    private function getJsonString(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
