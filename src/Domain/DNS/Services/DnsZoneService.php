<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Entities\DnsZoneDiff;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Interfaces\DnsZoneFactoryInterface;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

class DnsZoneService
{
    private const array ADDRESS_RECORD_TYPES = ['A', 'AAAA'];

    public function __construct(
        private readonly DnsZoneFactoryInterface $dnsZoneFactory,
    ) {
    }

    public function getHostingDnsRecords(string $domain, ?string $ipv4, ?string $ipv6): DnsZoneDiff
    {
        $dnsZone = $this->dnsZoneFactory->create($domain, 'hosting', $ipv4, $ipv6);

        return new DnsZone(new Fqdn($domain))->diff($dnsZone);
    }

    /**
     * Get the hosting-related DNS records used when updating a zone.
     *
     */
    public function getExternalHostingDnsRecords(
        string $domain,
        ?string $ipv4,
        ?string $ipv6,
        ?string $ipv4Mail,
        ?string $ipv6Mail,
    ): DnsZoneDiff {
        $dnsZone = $this->dnsZoneFactory->create(
            domain: $domain,
            dnsTemplate: 'external-hosting',
            ipv4: $ipv4,
            ipv6: $ipv6,
            ipv4Mail: $ipv4Mail,
            ipv6Mail: $ipv6Mail,
        );

        return new DnsZone(new Fqdn($domain))->diff($dnsZone);
    }

    /**
     * Get the A/AAAA records the parking ('default') template defines for the domain.
     *
     * @return DnsRecordInterface[]
     */
    public function getParkingAddressRecords(string $domain): array
    {
        $parkingZone = $this->dnsZoneFactory->create($domain, 'default');

        return array_values(array_filter(
            $parkingZone->getRecords(),
            fn (DnsRecordInterface $record): bool => in_array($record->getType(), self::ADDRESS_RECORD_TYPES, true),
        ));
    }
}
