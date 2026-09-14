<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use JsonException;
use Waterfront\Apps\API\Waterfront\Policies\DomainDeploymentPolicy;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;

class DomainDeploymentResource
{
    public function __construct(
        private readonly BaseTechnicalDeploymentResource $baseTechnicalDeploymentResource,
        private readonly DnsService $dnsService,
        private readonly RtrErrorParseService $rtrErrorParseService,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DomainDeploymentPolicy $deploymentPolicy,
    ) {
    }

    /**
     * @return array <string, mixed>
     */
    public function toArray(DomainDeployment $deployment): array
    {
        $domain = $deployment->subscription->domain ?? null;
        $dnsDeployment = $domain !== null ? $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domain) : null;

        $baseDeploymentResource = $this->baseTechnicalDeploymentResource->toArray($deployment);
        $availableActions = [
            ...$baseDeploymentResource['available_actions'],
            ...$this->deploymentPolicy->getAvailableActions($deployment),
        ];

        return [
            ...$baseDeploymentResource,
            'domain_contact_id' => $deployment->contact_owner_id,
            'domain_status' => $deployment->domain_status?->value,
            'has_dns_zone' => ! is_null($domain) && $this->dnsService->hasDnsZone($domain),
            'has_custom_nameservers' =>
                $dnsDeployment !== null && $dnsDeployment->nameserver_type === NameserverType::EXTERNAL,
            'last_result' => $this->rtrErrorParseService->getTranslatedRtrError($deployment->last_result ?? ''),
            'available_actions' => $availableActions,
        ];
    }

    /**
     * @throws JsonException
     */
    public function toJson(DomainDeployment $deployment): string
    {
        return json_encode($this->toArray($deployment), flags: JSON_THROW_ON_ERROR);
    }
}
