<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\AssignNameserversWithoutNameserversException;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsExternalNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Webmozart\Assert\Assert;

class DnsExternalNameserverAssigner implements NameserverAssignerInterface
{
    public function __construct(
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DomainServiceFactory $domainServiceFactory
    ) {
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws AssignNameserversWithoutNameserversException
     *
     * @return Nameserver[]
     */
    public function assign(DnsDeployment $dnsDeployment, array $nameservers = []): array
    {
        if ($nameservers === []) {
            $nameservers = $this->getNameserversFromRegistry($dnsDeployment);
        }

        foreach ($nameservers as $externalNameserver) {
            $nameserver = new DnsExternalNameserver();
            $nameserver->dns_deployment_id = $dnsDeployment->id;
            $nameserver->nameserver = $externalNameserver->hostname;
            $nameserver->ipv4 = $externalNameserver->ipv4;
            $nameserver->ipv6 = $externalNameserver->ipv6;
            $nameserver->save();
        }

        $dnsDeployment->nameserver_type = NameserverType::EXTERNAL;
        $dnsDeployment->save();

        return $this->dnsDeploymentRepository->getNameservers($dnsDeployment);
    }

    public function clear(DnsDeployment $dnsDeployment): void
    {
        if ($dnsDeployment->externalNameservers->isEmpty()) {
            return;
        }

        $dnsDeployment->externalNameservers()->delete();
    }

    /**
     * @throws AssignNameserversWithoutNameserversException
     *
     * @return Nameserver[]
     */
    private function getNameserversFromRegistry(DnsDeployment $dnsDeployment): array
    {
        $domainDeployment = $this->dnsDeploymentRepository->getDomainDeployment($dnsDeployment);

        Assert::notNull($domainDeployment);

        $registry = $this->domainServiceFactory->driver($domainDeployment->provider->slug, $domainDeployment->businessUnit);
        $registryResult = $registry->nameservers($domainDeployment);

        /**
         * @var array<array{id: string, seqNr: string, name: string, ip: string|null, ip6: string|null}>|null $registryNameservers
         */
        $registryNameservers = $registryResult->getNameServers();

        $nameservers = array_map(
            fn (array $nameserver): Nameserver => new Nameserver(
                hostname: $nameserver['name'],
                ipv4: $nameserver['ip'],
                ipv6: $nameserver['ip6']
            ),
            $registryNameservers ?? []
        );

        if ($nameservers === []) {
            throw new AssignNameserversWithoutNameserversException($domainDeployment->subscription->domain ?? 'null');
        }

        return $nameservers;
    }
}
