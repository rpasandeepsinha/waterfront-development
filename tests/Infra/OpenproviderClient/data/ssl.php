<?php

declare(strict_types=1);

return [
    'domain'    => 'example.org',
    'customer'  => include(__DIR__ . '/customer.php'),
    'productId' => 31,
    'period'    => 2,
    'csr'       => '-----BEGIN CERTIFICATE REQUEST-----
MIIBvzCCASgCAQAwfzELMAkGA1UEBhMCTkwxCzAJBgNVBAgTAlpIMQ0wCwYDVQQH
EwRSZGFtMQ8wDQYDVQQKEwZSb29yZGExHDAaBgNVBAMTE8d3dy5zaWVtZW5yb29y
ZGEubmwxJTAjBgkqhkiG9w0BCQEWFnNpZW1lbkBzbWVtZW5yb29yZGEubmwwgZ8w
DQYJKoZIhvcNAQEBBQADgY0AMIGJAoGBAKo5t1d4ka11M6NSUca2KBJS8d3a7lPh
7xlMAyNAvI68EQJEbPJ0UPxM9AiIS4HoVzXSGrP7lqwR8mQhM5HZkXAvvKfUnoN9
BD4/k9Z/uErqtED/FQuOknmnHvgvAJewfTaaSN4+tbs1d54Yux9B/XeIhqyiWv9o
cRNh+gBMW8OLAgMBAAGgADANBgkqhkiG9w0BAQQFAAOBgQBFTxJ12R6juNaWQtmm
JMWPrv0MDosDOIBZrCZoyF+VStANG8PoTg1VD7RgG+pZItCcp/X5MrHNsUUnySW5
kUaUx8Z21OOaoYjlHZTUaGfX5VKjjKH3NZ373Xms6Y9PcbX2nhvfo8IFSgnWKXD8
7Vyp67kPlzocoO3rcGd+PmU/aQ==
-----END CERTIFICATE REQUEST-----',
    'softwareId'              => 'linux',
    'organizationHandle'      => 'BA904019-NL',
    'technicalHandle'         => 'BA904019-NL',
    'approverEmail'           => 'info@sandwave.io',
    'domainValidationMethods' => [
        [
            'hostName' => 'example.org',
            'method'   => 'dns',
        ],
    ],
];
