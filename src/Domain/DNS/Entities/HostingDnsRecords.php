<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Entities;

class HostingDnsRecords
{
    /**
     * @return array<array<string, string|int>>
     */
    public static function getRecords(): array
    {
        return [
            [
                'name' => 'mail.{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '{ipv4}',
            ],
            [
                'name' => 'smtp.{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '{ipv4}',
            ],
            [
                'name' => 'www.{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '{ipv4}',
            ],
            [
                'name' => '*.{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '{ipv4}',
            ],
            [
                'name' => '{domain}',
                'type' => 'A',
                'ttl' => 600,
                'content' => '{ipv4}',
            ],
            [
                'name' => '{domain}',
                'type' => 'MX',
                'ttl' => 600,
                'priority' => 10,
                'content' => 'mx.spamservice.nl.',
            ],
            [
                'name' => '{domain}',
                'type' => 'MX',
                'ttl' => 600,
                'priority' => 20,
                'content' => 'fallbackmx.spamservice.nl.',
            ],
            [
                'name' => '{domain}',
                'type' => 'MX',
                'ttl' => 600,
                'priority' => 30,
                'content' => 'lastmx.spamservice.nl.',
            ],
            [
                'name' => '{domain}',
                'type' => 'TXT',
                'ttl' => 600,
                'content' => '"v=spf1 include:spf.spamservice.nl mx a ~all"',
            ],
            [
                'name' => 'smtp.{domain}',
                'type' => 'AAAA',
                'ttl' => 600,
                'content' => '{ipv6}',
            ],
            [
                'name' => 'www.{domain}',
                'type' => 'AAAA',
                'ttl' => 600,
                'content' => '{ipv6}',
            ],
            [
                'name' => '{domain}',
                'type' => 'AAAA',
                'ttl' => 600,
                'content' => '{ipv6}',
            ],
            [
                'name' => 'autodiscover.{domain}',
                'type' => 'CNAME',
                'ttl' => 600,
                'content' => 'autodiscover.2is.nl.',
            ],
            [
                'name' => 'autoconfig.{domain}',
                'type' => 'CNAME',
                'ttl' => 600,
                'content' => 'autoconfig.2is.nl.',
            ],
            [
                'name' => 'webmail.{domain}',
                'type' => 'CNAME',
                'ttl' => 600,
                'content' => 'webmail.2is.nl.',
            ],
        ];
    }
}
