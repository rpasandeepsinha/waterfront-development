<?php

declare(strict_types=1);

namespace Tests\Infra\Acronis\Factories;

use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\AcronisBackupDeploymentFactory;
use Tests\Factories\AcronisProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Backup\Acronis\Repositories\AcronisProviderRepository;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisClientFactoryException;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;

#[CoversClass(AcronisClientFactory::class)]
class AcronisClientFactoryTest extends IntegrationTestCase
{
    private const string CLIENT_ID = '11111111-1111-1111-1111-111111111111';
    private const string CLIENT_SECRET = 'clientsecret123';
    private const string BASE_URL = 'https://acronis.test/api/2/';

    private AcronisClientFactory $acronisClientFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acronisClientFactory = new AcronisClientFactory(
            acronisProviderRepository: $this->app->make(AcronisProviderRepository::class),
            logger: self::createStub(LoggerInterface::class),
            logMasker: self::createStub(JsonLogMasker::class),
            cache: self::createStub(Repository::class),
            retryConfig: new RetryConfig(),
        );
    }

    #[Test]
    public function createBuildsClientWithConnectorConfig(): void
    {
        $provider = AcronisProviderFactory::new()->createOne([
            'id' => 1,
            'endpoint' => self::BASE_URL,
            'client_id' => Uuid::fromString(self::CLIENT_ID),
            'client_secret' => self::CLIENT_SECRET,
        ]);

        $acronisBackupDeployment = AcronisBackupDeploymentFactory::new()
            ->createOne(['acronis_provider_id' => $provider->id]);

        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);

        $userApiConnector = $this->getPrivateProperty($acronisClient->userClient, 'connector');
        $offeringItemsApiConnector = $this->getPrivateProperty($acronisClient->offeringItemsClient, 'connector');
        $statusApiConnector = $this->getPrivateProperty($acronisClient->genericClient, 'connector');

        self::assertInstanceOf(AcronisConnector::class, $userApiConnector);
        self::assertSame($userApiConnector, $offeringItemsApiConnector);
        self::assertSame($userApiConnector, $statusApiConnector);

        $config = $this->getPrivateProperty($userApiConnector, 'acronisConfig');
        self::assertInstanceOf(ConnectorConfig::class, $config);

        self::assertSame('https://acronis.test/api/2', $this->getPrivateProperty($config, 'baseUrl'));
        self::assertSame(self::CLIENT_ID, $this->getPrivateProperty($config, 'clientId'));
        self::assertSame(self::CLIENT_SECRET, $this->getPrivateProperty($config, 'clientSecret'));
    }

    #[Test]
    public function createTrimsTrailingSlashFromEndpoint(): void
    {
        $provider = AcronisProviderFactory::new()->createOne([
            'id' => 1,
            'endpoint' => 'https://example.test////',
            'client_id' => Uuid::fromString(self::CLIENT_ID),
            'client_secret' => self::CLIENT_SECRET,
        ]);

        $acronisBackupDeployment = AcronisBackupDeploymentFactory::new()
            ->createOne(['acronis_provider_id' => $provider->id]);

        $acronisClient = $this->acronisClientFactory->createFromDeployment($acronisBackupDeployment);

        $userApiConnector = $this->getPrivateProperty($acronisClient->userClient, 'connector');
        self::assertInstanceOf(AcronisConnector::class, $userApiConnector);

        $config = $this->getPrivateProperty($userApiConnector, 'acronisConfig');
        self::assertInstanceOf(ConnectorConfig::class, $config);

        self::assertSame('https://example.test', $this->getPrivateProperty($config, 'baseUrl'));
    }

    #[Test]
    public function getDefaultProvider(): void
    {
        $expectedEndpoint = 'https://default.acronis.nl';
        AcronisProviderFactory::new()->createOne([
            'endpoint' => 'https://argeweb.acronis.nl',
            'client_id' => Uuid::fromString(self::CLIENT_ID),
            'client_secret' => self::CLIENT_SECRET,
        ]);

        AcronisProviderFactory::new()->createOne([
            'endpoint' => 'https://yourhosting.acronis.nl',
            'client_id' => Uuid::fromString(self::CLIENT_ID),
            'client_secret' => self::CLIENT_SECRET,
        ]);

        $defaultProvider = AcronisProviderFactory::new()
            ->default()
            ->createOne([
                'endpoint' => $expectedEndpoint,
                'client_id' => Uuid::fromString(self::CLIENT_ID),
                'client_secret' => self::CLIENT_SECRET,
            ]);

        $clientFactory = $this->app->make(AcronisClientFactory::class);

        $acronisClient = $clientFactory->getDefault();

        $userApiConnector = $this->getPrivateProperty($acronisClient->userClient, 'connector');
        self::assertInstanceOf(AcronisConnector::class, $userApiConnector);

        $config = $this->getPrivateProperty($userApiConnector, 'acronisConfig');
        self::assertInstanceOf(ConnectorConfig::class, $config);

        self::assertSame($defaultProvider->endpoint, $this->getPrivateProperty($config, 'baseUrl'));
    }

    #[Test]
    public function getDefaultProviderFails(): void
    {
        AcronisProviderFactory::new()->makeOne([
            'default' => false,
            'endpoint' => 'https://argeweb.acronis.nl/',
            'client_id' => Uuid::fromString(self::CLIENT_ID),
            'client_secret' => self::CLIENT_SECRET,
        ]);

        $clientFactory = $this->app->make(AcronisClientFactory::class);

        $this->expectException(AcronisClientFactoryException::class);
        $this->expectExceptionMessageIs('Could not retrieve default Acronis provider.');
        $clientFactory->getDefault();
    }

    private function getPrivateProperty(object $object, string $property): mixed
    {
        $targetClass = $object::class;

        return (function () use ($property, $targetClass) {
            IntegrationTestCase::assertTrue(
                property_exists($this, $property),
                sprintf('Property "%s" not found on %s.', $property, $targetClass)
            );

            return $this->{$property}; // @phpstan-ignore property.dynamicName
        })->call($object);
    }
}
