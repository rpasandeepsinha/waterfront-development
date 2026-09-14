<?php

declare(strict_types=1);

return [
    'domainName' => 'test-dns-intern-10.nl',
    'registry' => 'sidn',
    'customer' => 'testdummy',
    'registrant' => 'testdummy',
    'privacyProtect' => true,
    'status' => ['OK'],
    'authcode' => '294759302',
    'languageCode' => 'nl',
    'autoRenew' => true,
    'autoRenewPeriod' => 12,
    'ns' => [],
    'childHosts' => [
        'example.com',
    ],
    'createdDate' => '2023-02-06 01:02:03',
    'updatedDate' => '2023-02-06 01:02:03',
    'expiryDate' => '2024-02-06 01:02:03',
    'premium' => false,
    'zone' => [
        'id' => 1334,
        'template' => 'template-01',
        'link' => true,
        'dnssec' => true,
        'service' => 'BASIC',
    ],
    'contacts' => [
        [
            'role' => 'ADMIN',
            'handle' => 'testdummy',
        ],
        [
            'role' => 'TECH',
            'handle' => 'testdummy',
        ],
    ],
    'keyData' => [
        [
            'protocol' => 3,
            'flags' => 256,
            'algorithm' => 10,
            'publicKey' => 'TFMwdFVsTkJMUzB0SUdGelpHWmhjMlJtWVhOa1ptRnpaR1poYzJSbQ==',
        ],
        [
            'protocol' => 3,
            'flags' => 257,
            'algorithm' => 8,
            'publicKey' => 'WmhjMlJtWVhOa1ptRnpaR1poYzJSbUxTMHRVbE5CTFMwdElHRnpaRw==',
        ],
    ],
    'ds_data' => [
        [
            'keyTag' => 1,
            'algorithm' => 5,
            'digestType' => 2,
            'digest' => 'blablablabla',
        ],
        [
            'keyTag' => 1,
            'algorithm' => 5,
            'digestType' => 2,
            'digest' => 'blablablabla',
        ],
    ],
];
