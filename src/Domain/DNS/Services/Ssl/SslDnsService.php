<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services\Ssl;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\AddedDnsRecord;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Infra\Common\PublicSuffixList;

class SslDnsService
{
    private bool $zoneExists;

    /**
     * Initialize the service with the SSL client and subscription repository.
     */
    public function __construct(
        private readonly DnsRecordHydrator $dnsRecordHydrator,
        private readonly DnsService $dnsService,
        private readonly PublicSuffixList $rules,
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    /**
     * Get the ssl-related DNS records used when updating a zone.
     */
    public function getSslDnsRecords(Result $certificate): ?DnsZoneDiff
    {
        $old = $this->findCnameRecord($certificate);

        if (! $this->zoneExists) {
            return null;
        }

        $new = $this->dnsRecordHydrator->hydrate(
            [
                'name' => $certificate->getDnsRecord(),
                'type' => 'CNAME',
                'ttl' => 600,
                'content' => $certificate->getDnsValue(),
            ],
        );

        if ($old !== null) {
            Log::info(sprintf(
                'Updating SSL CNAME record for %s (%d)',
                $certificate->getDnsRecord(),
                $certificate->getCertificateId(),
            ));

            $change = new ChangedDnsRecord($old, $new);
        } else {
            Log::info(sprintf(
                'Creating new SSL CNAME record for %s (%d)',
                $certificate->getDnsRecord(),
                $certificate->getCertificateId(),
            ));

            $change = new AddedDnsRecord($new);
        }

        return new DnsZoneDiff([$change]);
    }

    /**
     * Generate DNS changes and send event to update for SSL CNAME record.
     */
    public function updateDns(Result $result, string $domain): void
    {
        $dnsRecords = $this->getSslDnsRecords($result);

        if ($dnsRecords !== null) {
            $this->eventDispatcher->dispatch(new UpdateDns($domain, $dnsRecords));
        }
    }

    /**
     * Find CNAME record (if exists) for certificate.
     */
    private function findCnameRecord(Result $certificate): ?DnsRecordInterface
    {
        $domain = $this->rules->getRules()->resolve($certificate->getDnsRecord());

        try {
            $domain = $domain->registrableDomain()->value();
            assert(! is_null($domain));
            $zone = $this->dnsService->getDnsZone($domain);
        } catch (DnsZoneNotFoundException $e) {
            Log::warning(sprintf(
                '%s SSL certificate %s (%d)',
                $e->getMessage(),
                $certificate->getDnsRecord(),
                $certificate->getCertificateId(),
            ));

            $this->zoneExists = false;

            return null;
        }

        $this->zoneExists = true;

        return array_find(
            $zone->getRecords(),
            fn ($record) => $record->getType() === 'CNAME' && Str::endsWith($record->getContent(), '.sectigo.com'),
        );
    }
}
