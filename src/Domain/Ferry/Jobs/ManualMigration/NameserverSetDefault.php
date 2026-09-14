<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\ManualMigration;

use RuntimeException;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\DomainWithoutDnsDeploymentException;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;

/**
 * Assigns default 2.0 nameservers to the domain deployment and provisions to the registry.
 *
 * @see MigrationJobEventListener
 */
class NameserverSetDefault extends ManualMigrationJob
{
    public function handle(
        AssignNameserversToDomainAction $assignNameserversToDomainAction,
        DnsDeploymentRepository $dnsDeploymentRepository,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;
        if ($domainDeployment === null) {
            throw new RuntimeException(sprintf(
                'Domain deployment not found for domain: %s',
                $this->subscription->domain,
            ));
        }

        $dnsDeployment = $dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);

        if ($dnsDeployment === null) {
            throw new DomainWithoutDnsDeploymentException($this->subscription->domain ?? '');
        }

        $dnsDeploymentRepository->setNameserverType($dnsDeployment, NameserverType::INTERNAL);
        $assignNameserversToDomainAction->assign($domainDeployment);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::NAMESERVER_SET_DEFAULT;
    }
}
