<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Repositories;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\CreateDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DeleteDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Models\DomainNameCoupleDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DomainNameCoupleRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @throws CreateDomainNameCoupleDeploymentException
     */
    public function create(
        string $domain,
        ProvisionType $coupleType,
        UuidInterface $deploymentUuid,
        int $requestId,
    ): DomainNameCoupleDeployment {
        $deployment = new DomainNameCoupleDeployment();
        $deployment->uuid = Uuid::uuid4();
        $deployment->origin_provisioning_request_id = $requestId;
        $deployment->domain = $domain;
        $deployment->couple_type = $coupleType;
        $deployment->deployment_uuid = $deploymentUuid;

        if (! $deployment->save()) {
            throw new CreateDomainNameCoupleDeploymentException($deployment);
        }

        return $deployment;
    }

    /**
     * @throws DeleteDomainNameCoupleDeploymentException
     */
    public function delete(
        string $domain,
        ProvisionType $coupleType,
        UuidInterface $deploymentUuid
    ): void {
        $deployment = DomainNameCoupleDeployment::where([
            'domain' => $domain,
            'couple_type' => $coupleType,
            'deployment_uuid' => $deploymentUuid,
        ])->first();

        if ($deployment === null) {
            $this->logger->warning(
                'Attempted to delete non-existing domain name couple deployment',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::META => [
                        'couple_type' => $coupleType,
                        'deployment_uuid' => $deploymentUuid->toString(),
                    ],
                ]
            );
            return;
        }

        if ($deployment->delete() === false) {
            throw new DeleteDomainNameCoupleDeploymentException($deployment);
        }
    }

    public function coupleDomainToDomainNameDeployment(DomainNameCoupleDeployment $domainNameCoupleDeployment, Subscription $subscription, UuidInterface $deploymentUuid): void
    {
        Assert::string($subscription->domain);
        $domainNameCoupleDeployment->domain = $subscription->domain;
        $domainNameCoupleDeployment->deployment_uuid = $deploymentUuid;
        $domainNameCoupleDeployment->save();
    }
}
