<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\ManualMigration;

use RuntimeException;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\DomainWithoutDnsDeploymentException;
use Waterfront\Support\Helpers\DnsHelper;

/**
 * Updates the database to set the nameservers to the current nameservers found in the DNS.
 *
 * @see MigrationJobEventListener
 */
class NameserverSetCurrent extends ManualMigrationJob
{
    public function handle(DnsDeploymentRepository $dnsDeploymentRepository, DnsHelper $dnsHelper, DnsExternalNameserverAssigner $nameserverAssigner): void
    {
        $domainDeployment = $this->subscription->domainDeployment;
        $domain = $this->subscription->domain;
        assert($domain !== null);

        if ($domainDeployment === null) {
            throw new RuntimeException(sprintf('Domain deployment not found for domain: %s', $domain));
        }

        $dnsDeployment = $dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);

        if ($dnsDeployment === null) {
            throw new DomainWithoutDnsDeploymentException($domain);
        }

        $nameservers = $dnsHelper->dnsGetRecord($domain, DNS_NS);

        if ($nameservers === false) {
            throw new RuntimeException(sprintf('Failed to retrieve nameservers for domain: %s', $domain));
        }

        $nameserverAssigner->assign($dnsDeployment, array_map(fn (array $nameserver) => new Nameserver($nameserver['target']), $nameservers));
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::NAMESERVER_SET_CURRENT;
    }
}
