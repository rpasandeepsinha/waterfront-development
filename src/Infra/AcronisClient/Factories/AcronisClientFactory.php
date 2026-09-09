<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Factories;

use Illuminate\Cache\Repository;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Backup\Acronis\Repositories\AcronisProviderRepository;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisClientFactoryException;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisGenericClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisOfferingItemsClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisTenantClient;
use Waterfront\Infra\AcronisClient\Clients\AcronisUserClient;
use Waterfront\Infra\AcronisClient\Config\ConnectorConfig;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;

class AcronisClientFactory
{
    public function __construct(
        private readonly AcronisProviderRepository $acronisProviderRepository,
        private readonly LoggerInterface $logger,
        private readonly JsonLogMasker $logMasker,
        private readonly Repository $cache,
        private readonly RetryConfig $retryConfig,
    ) {
    }

    public function create(AcronisProvider $acronisProvider): AcronisClient
    {
        $connector = new AcronisConnector(
            acronisConfig: new ConnectorConfig(
                baseUrl:rtrim($acronisProvider->endpoint, '/'),
                clientId: $acronisProvider->client_id->toString(),
                clientSecret: $acronisProvider->client_secret,
                retryConfig: $this->retryConfig,
            ),
            logger: $this->logger,
            logMasker: $this->logMasker,
            cache: $this->cache,
        );

        return new AcronisClient(
            tenantId: $acronisProvider->tenant_uuid,
            userClient: new AcronisUserClient($connector, $this->logger),
            offeringItemsClient: new AcronisOfferingItemsClient($connector),
            tenantClient: new AcronisTenantClient($connector),
            genericClient: new AcronisGenericClient($connector),
        );
    }

    /**
     * @throws AcronisClientFactoryException
     */
    public function getDefault(): AcronisClient
    {
        $defaultProvider = $this->acronisProviderRepository->getDefault();

        if ($defaultProvider === null) {
            throw new AcronisClientFactoryException('Could not retrieve default Acronis provider.');
        }

        return $this->create($defaultProvider);
    }

    public function createFromDeployment(AcronisBackupDeployment $backupDeployment): AcronisClient
    {
        return $this->create($backupDeployment->acronisProvider);
    }
}
