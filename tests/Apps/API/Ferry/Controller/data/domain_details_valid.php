<?php

declare(strict_types=1);

return [
    'domainName' => 'example.nl',
    'registry' => 'sidn',
    'customer' => 'johndoe',
    'registrant' => 'johndoe_registrant',
    'privacyProtect' => true,
    'status' => ['PENDING_RENEW'],
    'authcode' => '294759302',
    'languageCode' => 'nl',
    'autoRenew' => false,
    'autoRenewPeriod' => 12,
    'ns' => [
        'ns1.sandwave-test.com',
        'ns02.sandwave-test.com',
    ],
    'childHosts' => [
        'example.com',
    ],
    'createdDate' => '2020-08-30 01:02:03',
    'updatedDate' => '2020-08-30 01:02:03',
    'expiryDate' => '2020-11-30 01:02:03',
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
            'handle' => 'johndoe',
        ],
        [
            'role' => 'TECH',
            'handle' => 'johndoe',
        ],
        [
            'role' => 'BILLING',
            'handle' => 'johnydoe',
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
