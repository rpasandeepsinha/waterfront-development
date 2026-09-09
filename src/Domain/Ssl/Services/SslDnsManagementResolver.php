<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Services;

use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Ssl\Models\SslDeployment;

class SslDnsManagementResolver
{
    public function __construct(
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
    ) {
    }

    public function hasManagedDns(SslDeployment $ssl): bool
    {
        $dns = $this->dnsDeploymentForSsl($ssl);

        if ($dns === null) {
            return false;
        }

        return $dns->nameserver_type !== NameserverType::EXTERNAL;
    }

    private function dnsDeploymentForSsl(SslDeployment $ssl): ?DnsDeployment
    {
        $sslSubscription = $ssl->subscription;

        $domainParent = $this->domainDeploymentRepository->getExtensionParentSubscription($sslSubscription);

        if ($domainParent === null) {
            return null;
        }

        $dnsChild = $this->domainDeploymentRepository->getDnsChildSubscription($domainParent);
        if ($dnsChild === null) {
            return null;
        }

        return $dnsChild->dnsDeployment;
    }
}
