<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services;

use Waterfront\Domain\Redirects\Enums\DnsRedirectProvisionOption;

interface RedirectDnsServiceInterface
{
    /**
     * Provision the DNS zone with records that point towards the redirect service.
     *
     * @param string $domain Base domain for DNS zone.
     * @param string $host   Host (optionally) including subdomain that needs to point to the redirect server.
     */
    public function provisionDnsRecords(string $domain, string $host, DnsRedirectProvisionOption $dnsProvisionOption): void;

    /**
     * Clean up records of the redirect service on the DNS zone.
     *
     * @param string $domain Base domain for DNS zone.
     * @param string $host   Host (optionally) including subdomain that points to the redirect server.
     */
    public function cleanupDnsRecords(string $domain, string $host): void;
}
