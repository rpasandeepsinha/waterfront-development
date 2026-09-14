<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Actions\DNS;

use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

class UpdateZoneToMasterAction
{
    public function __construct(
        private readonly DnsMigrationService $dnsMigrationService,
    ) {
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     */
    public function execute(string $domain, int $subscriptionId, string $migratedCustomerReference): void
    {
        $this->dnsMigrationService->changeToMasterAndEmptyMasters(
            $domain,
            $subscriptionId,
            $migratedCustomerReference,
        );
    }
}
