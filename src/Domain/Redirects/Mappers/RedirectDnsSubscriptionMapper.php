<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Mappers;

use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Transformers\DnsRecordResource;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;

class RedirectDnsSubscriptionMapper
{
    public function __construct(
        private readonly RedirectDnsService $redirectDnsService,
    ) {
    }

    /**
     * This mapper adds a given (redirect) subscription UUID to each dns record
     * that has been created for a redirect subscription. This can be used
     * to match the DNS records with its associated redirect subscription.
     *
     * @param Collection<int, DnsRecordInterface> $dnsRecords
     *
     * @return Collection<int, DnsRecordResource>
     */
    public function addSubscriptionUuidToDnsRecords(
        Collection $dnsRecords,
        UuidInterface $subscriptionUuid,
    ): Collection {
        return $dnsRecords->map(function (DnsRecordInterface $record) use ($subscriptionUuid): DnsRecordResource {
            $resource = DnsRecordResource::make($record);

            if ($this->redirectDnsService->isRedirectManagedRecord(record: $record, includeLegacyServer: false)) {
                $resource->redirectUuid = $subscriptionUuid->toString();
            }

            return $resource;
        });
    }
}
