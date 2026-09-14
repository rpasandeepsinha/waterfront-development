<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use NetDNS2\Exception;
use NetDNS2\Resolver;
use NetDNS2\RR\NS;
use PurplePixie\PhpDns\DNSQuery;

/**
 * Helper class to allow for mocking in unit tests.
 */
class DnsHelper
{
    /**
     * @param array<int,string>|null $authoritative_name_servers
     * @param array<int,mixed>|null  $additional_records
     *
     * @return array<array<string, string>>|false
     */
    public function dnsGetRecord(
        string $hostname,
        int $type = DNS_ANY,
        ?array &$authoritative_name_servers = null,
        ?array &$additional_records = null,
        bool $raw = false,
    ): array|false {
        return dns_get_record(
            $hostname,
            $type,
            $authoritative_name_servers,
            $additional_records,
            $raw,
        );
    }

    public function isDnsSecEnabled(string $domain): bool
    {
        // By default, if you don't pick a nameserver yourself, it uses /etc/resolv.conf
        // and for us that means AWS. This would be ideal, but they don't support DNSSEC.
        $resolver = new Resolver(['nameservers' => ['1.1.1.1']]);
        $resolver->dnssec = true;

        try {
            $response = $resolver->query($domain, 'SOA');
        } catch (Exception) {
            return false;
        }

        return $response->header->ad === 1;
    }

    /**
     * @return array<string>
     */
    public function getNameServers(string $domain): array
    {
        $resolver = new Resolver();
        $nameservers = [];

        try {
            $response = $resolver->query($domain, 'NS');
            foreach ($response->answer as $answer) {
                if ($answer instanceof NS) {
                    $nameservers[] = $answer->nsdname->value();
                }
            }
        } catch (Exception) {
            return $nameservers;
        }

        return $nameservers;
    }

    public function createDnsQuery(string $nameserverHostname): DNSQuery
    {
        return new DNSQuery($nameserverHostname);
    }

    public function getHostByName(string $hostname): string
    {
        return gethostbyname($hostname);
    }
}
