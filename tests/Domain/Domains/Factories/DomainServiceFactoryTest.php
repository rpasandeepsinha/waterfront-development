<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Factories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use ReflectionClass;
use Tests\Factories\DomainProviderBusinessUnitFactory;
use Tests\Factories\OpenproviderProviderCredentialsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrProviderCredentialsFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\OpenproviderService;
use Waterfront\Domain\Placeholder\Services\DomainPlaceholderService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Infra\RtrClient\Factories\RtrClientFactory;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainServiceFactory::class)]
class DomainServiceFactoryTest extends IntegrationTestCase
{
    private DomainServiceFactory $domainServiceFactory;

    /**
     * @var array{
     *   handles: list<string|null>,
     *   clients: list<RealtimeRegister>
     * }
     */
    private array $seen = ['handles' => [], 'clients' => []];

    public function setUp(): void
    {
        parent::setUp();
        $this->domainServiceFactory = self::resolve(DomainServiceFactory::class);
    }

    #[Test]
    public function driver(): void
    {
        $this->expectNotToPerformAssertions();
        $this->domainServiceFactory->driver(ProviderSlug::OPEN_PROVIDER);
    }

    #[Test]
    public function defaultDriver(): void
    {
        $this->expectNotToPerformAssertions();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);
        $this->domainServiceFactory->defaultDriver();
    }

    #[Test]
    public function defaultDriverNotFound(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->domainServiceFactory->defaultDriver();
    }

    #[Test]
    public function resolveProviderByProductThroughProductSpec(): void
    {
        $productGroup = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        ProviderFactory::new()->domainRtr()->createOne();
        $productProvider = ProviderFactory::new()->domainOpenProvider()->createOne(['default' => false]);

        new ProductSpecFactory()->for($product)->createOne([
            'name' => 'domain.provider_id',
            'value' => $productProvider->id,
        ]);

        $provider = $this->domainServiceFactory->resolveProviderByProduct($product);
        self::assertSame(ProviderType::DOMAIN, $provider->type);
        self::assertSame($productProvider->id, $provider->id);
    }

    #[Test]
    public function resolveProviderByProductDefault(): void
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        ProviderFactory::new()->domainRtr()->createOne();

        $provider = $this->domainServiceFactory->resolveProviderByProduct($product);
        self::assertSame(ProviderType::DOMAIN, $provider->type);
    }

    #[Test]
    public function getDriverWithoutBusinessUnit(): void
    {
        $providerSlug = ProviderSlug::REALTIME_REGISTER;

        $rtrService = self::createMock(RtrService::class);

        $rtrService->expects(self::once())->method('setHandle')->willReturnSelf()->with(null);

        $rtrService
            ->expects(self::once())
            ->method('setClient')
            ->willReturnSelf()
            ->with(self::isInstanceOf(RealtimeRegister::class));

        $domainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);

        $domainDeploymentRepository->expects(self::never())->method('getDomainProviderCredentials');

        $domainServiceFactory = new DomainServiceFactory(
            $rtrService,
            self::resolve(RtrClientFactory::class),
            self::createStub(OpenproviderService::class),
            self::createStub(OpenproviderClientFactory::class),
            self::createStub(DomainPlaceholderService::class),
            $domainDeploymentRepository,
            self::createStub(LoggerInterface::class),
        );

        $domainServiceFactory->driver($providerSlug);
    }

    #[Test]
    public function getDriverWithRtrBusinessUnit(): void
    {
        $providerSlug = ProviderSlug::REALTIME_REGISTER;
        $handleValue = 'TEST-HANDLE';
        $businessUnit = new DomainProviderBusinessUnitFactory()->waterfront()->makeOne();
        $credentialsDto = new RtrProviderCredentialsFactory()->makeOne(['handle' => $handleValue]);
        $defaultConfig = [
            'apiKey' => $this->getConfig('realtimeregisterclient.connection.api_key'),
            'baseUri' => rtrim($this->getConfig('realtimeregisterclient.connection.api_url'), '/'),
        ];

        $domainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);

        $domainDeploymentRepository
            ->expects(self::once())
            ->method('getDomainProviderCredentials')
            ->with($providerSlug, $businessUnit)
            ->willReturn($credentialsDto);

        $rtrService = self::createStub(RtrService::class);

        $rtrService
            ->method('setHandle')
            ->willReturnCallback(fn (?string $handle): RtrService => $this->recordHandle($handle, $rtrService));

        $rtrService
            ->method('setClient')
            ->willReturnCallback(
                fn (RealtimeRegister $client): RtrService => $this->recordClient($client, $rtrService),
            );

        $factory = new DomainServiceFactory(
            $rtrService,
            self::resolve(RtrClientFactory::class),
            self::createStub(OpenproviderService::class),
            self::createStub(OpenproviderClientFactory::class),
            self::createStub(DomainPlaceholderService::class),
            $domainDeploymentRepository,
            self::createStub(LoggerInterface::class),
        );

        $factory->driver($providerSlug);
        $factory->driver($providerSlug, $businessUnit);
        $factory->driver($providerSlug);

        self::assertSame([null, $handleValue, null], $this->seen['handles']);
        self::assertCount(3, $this->seen['clients']);

        $customConfig = ['apiKey' => $credentialsDto->api_key, 'baseUri' => rtrim($credentialsDto->api_url, '/')];
        $expectedConfig = [$defaultConfig, $customConfig, $defaultConfig];

        foreach ($this->seen['clients'] as $index => $clientInstance) {
            self::assertSame($expectedConfig[$index], $this->inspect($clientInstance));
        }
    }

    #[Test]
    public function getDriverWithOpenproviderBusinessUnit(): void
    {
        $providerSlug = ProviderSlug::OPEN_PROVIDER;
        $businessUnit = new DomainProviderBusinessUnitFactory()->waterfront()->makeOne();
        $credentialsDto = new OpenproviderProviderCredentialsFactory()->makeOne();

        $domainDeploymentRepository = self::createMock(DomainDeploymentRepository::class);

        $domainDeploymentRepository
            ->expects(self::once())
            ->method('getDomainProviderCredentials')
            ->with($providerSlug, $businessUnit)
            ->willReturn($credentialsDto);

        $openproviderClientFactory = self::createMock(OpenproviderClientFactory::class);

        $openproviderClientFactory
            ->expects(self::exactly(3))
            ->method('create')
            ->with(
                ...self::withConsecutive(
                    [null],
                    [$credentialsDto],
                    [null],
                ),
            );

        $logger = self::createMock(LoggerInterface::class);

        $logger
            ->expects(self::once())
            ->method('debug')
            ->with(
                sprintf(
                    'Updating OpenProvider client to domain provider business unit "%s"',
                    $businessUnit->slug,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::OPENPROVIDER,
                    LoggingContextKeys::META => [
                        'business_unit' => $businessUnit,
                    ],
                ],
            );

        $domainServiceFactory = new DomainServiceFactory(
            self::createStub(RtrService::class),
            self::createStub(RtrClientFactory::class),
            self::createStub(OpenproviderService::class),
            $openproviderClientFactory,
            self::createStub(DomainPlaceholderService::class),
            $domainDeploymentRepository,
            $logger,
        );

        $domainServiceFactory->driver($providerSlug);
        $domainServiceFactory->driver($providerSlug, $businessUnit);
        $domainServiceFactory->driver($providerSlug);
    }

    private function recordHandle(?string $handle, RtrService $rtrService): RtrService
    {
        $this->seen['handles'][] = $handle;

        return $rtrService;
    }

    private function recordClient(RealtimeRegister $client, RtrService $rtrService): RtrService
    {
        $this->seen['clients'][] = $client;

        return $rtrService;
    }

    /**
     * @return array{apiKey: string, baseUri: string}
     */
    private function inspect(RealtimeRegister $realTimeRegisterClient): array
    {
        /** @var object $brands */
        $brands = new ReflectionClass($realTimeRegisterClient)
            ->getProperty('brands')
            ->getValue($realTimeRegisterClient);
        /** @var object $auth */
        $auth = new ReflectionClass($brands)
            ->getProperty('client')
            ->getValue($brands);

        /** @var string $apiKey */
        $apiKey = new ReflectionClass($auth)
            ->getProperty('apiKey')
            ->getValue($auth);
        /** @var object $guzzleClient */
        $guzzleClient = new ReflectionClass($auth)
            ->getProperty('client')
            ->getValue($auth);
        /** @var array<string, mixed> $config */
        $config = new ReflectionClass($guzzleClient)
            ->getProperty('config')
            ->getValue($guzzleClient);

        /** @var UriInterface $uri */
        $uri = $config['base_uri'];
        $baseUri = (string) $uri;

        return [
            'apiKey' => $apiKey,
            'baseUri' => rtrim($baseUri, '/'),
        ];
    }

    private function getConfig(string $key): string
    {
        return self::resolve(ConfigurationInterface::class)->getAsString($key);
    }
}
