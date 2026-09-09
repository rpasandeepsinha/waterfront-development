<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'gandi' => [
        'vanity_nameservers' => [
            'ns1' => Env::get('GANDI_VANITY_NS1'),
            'ns2' => Env::get('GANDI_VANITY_NS2'),
            'ns3' => Env::get('GANDI_VANITY_NS3'),
        ],
        'live_dns_notify_bridge' => [
            'use_ipv6' => (bool) Env::get('GANDI_LIVE_DNS_USE_IPV6'),
            'ipv4' => Env::get('GANDI_LIVE_DNS_IPV4'),
            'ipv6' => Env::get('GANDI_LIVE_DNS_IPV6'),
        ],
    ],
];
