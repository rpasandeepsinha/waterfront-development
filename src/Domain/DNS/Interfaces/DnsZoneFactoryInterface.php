<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Interfaces;

use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsZone;

/**
 * Creates DNS zones for a domain.
 */
interface DnsZoneFactoryInterface
{
    /**
     * Creates a DNS Zone from a DNS Template.
     *
     * @param Nameserver[] $nameservers
     */
    public function create(
        string $domain,
        string $dnsTemplate,
        ?string $ipv4 = null,
        ?string $ipv6 = null,
        ?string $ipv4Mail = null,
        ?string $ipv6Mail = null,
        bool $dnsSec = false,
        array $nameservers = [],
    ): DnsZone;
}
