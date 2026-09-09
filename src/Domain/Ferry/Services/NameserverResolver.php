<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services;

use ErrorException;

class NameserverResolver
{
    /**
     * @throws ErrorException
     *
     * @return array<int, string>|false
     */
    public function getNameserverIPs(string $hostname): array|false
    {
        $dnsRecords = dns_get_record($hostname, DNS_A);

        if ($dnsRecords === false) {
            return false;
        }

        return array_map(fn ($dnsRecord) => $dnsRecord['ip'], $dnsRecords);
    }

    /**
     * @throws ErrorException
     */
    public function getNameserverHostname(string $ip): string|false
    {
        return gethostbyaddr($ip);
    }
}
