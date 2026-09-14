<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Interfaces\NameserverAssignerInterface;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Webmozart\Assert\Assert;

class DnsNameserverAssigner implements NameserverAssignerInterface
{
    private const int DEFAULT_NAMESERVER_COUNT = 3;

    public function __construct(
        private readonly DnsNameserverRetriever $retriever,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
    ) {
    }

    /**
     * @return Nameserver[]
     *
     * @inheritDoc
     */
    public function assign(DnsDeployment $dnsDeployment): array
    {
        Assert::stringNotEmpty($dnsDeployment->subscription->domain);

        if ($dnsDeployment->dnsNameservers()->count() !== 0) {
            throw new DnsNamerverAlreadyAssignedException($dnsDeployment->id, $dnsDeployment->subscription->domain);
        }

        $nameservers = $this->retriever->retrieve(self::DEFAULT_NAMESERVER_COUNT);

        $ids = $nameservers->map(
            static fn (DnsNameserver $nameserver) => $nameserver->id,
        );

        $dnsDeployment->dnsNameservers()->sync($ids);

        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->load('dnsNameservers');

        return $this->dnsDeploymentRepository->getNameservers($dnsDeployment);
    }

    public function clear(DnsDeployment $dnsDeployment): void
    {
        if ($dnsDeployment->dnsNameservers->isEmpty()) {
            return;
        }

        $dnsDeployment->dnsNameservers()->detach();
        $dnsDeployment->unsetRelation('dnsNameservers');
    }
}
