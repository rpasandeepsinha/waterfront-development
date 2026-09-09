<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\API\Waterfront\Policies\HostingDeploymentPolicy;
use Waterfront\Domain\Hosting\Exceptions\UnableToConvertHostingModelException;
use Waterfront\Domain\Hosting\GenericHostingModelConverter;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Support\Enums\LoggingContextKeys;

class HostingDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
        private readonly GenericHostingModelConverter $hostingModelConverter,
        private readonly LoggerInterface $logger,
        private readonly HostingDeploymentPolicy $hostingDeploymentPolicy,
        private readonly ProvisionGateway $provisionGateway,
    ) {
    }

    /**
     * @return array <string, mixed>
     */
    public function toArray(HostingDeployment $deployment): array
    {
        $baseDeploymentResource = $this->baseTechnicalDeploymentResource->toArray($deployment);
        $availableActions = [...$baseDeploymentResource['available_actions'], ...$this->hostingDeploymentPolicy->getAvailableActions($deployment)];

        $query = new ProvisioningResultQueryFilters(tag: Uuid::fromString($deployment->subscription->uuid), requestType: ProvisionType::SITEBUILDER);
        $provisioningResults = $this->provisionGateway->fetch($query, 1);

        $resourceArray = [
            ...$this->baseTechnicalDeploymentResource->toArray($deployment),
            'available_actions' => $availableActions,
            'has_deployment_through_gateway' => $provisioningResults->isNotEmpty(),
        ];

        try {
            $convertedModel = $this->hostingModelConverter->execute($deployment);

            $resourceArray = [
                ...$resourceArray,
                'server_name' => $convertedModel->server->hostname,
                'username' => $convertedModel->username,
                'ftps_host' => $convertedModel->server->hostname,
                'server_ipv4' => $convertedModel->server->ipv4,
                'server_ipv6' => $convertedModel->server->ipv6,
            ];
        } catch (UnableToConvertHostingModelException $exception) {
            $this->logger->notice(
                $exception->getMessage(),
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $deployment->subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $deployment->subscription->uuid,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );
        }

        return $resourceArray;
    }

    /**
     * @throws JsonException
     * @throws UnableToConvertHostingModelException
     */
    public function toJson(HostingDeployment $deployment): string
    {
        return json_encode($this->toArray($deployment), flags:JSON_THROW_ON_ERROR);
    }
}
