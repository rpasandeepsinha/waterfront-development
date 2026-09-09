<?php

declare(strict_types=1);

return [
    'domain' => [
        'domainName' => 'example.nl',
        'registrant' => 'johndoe',
        'status' => [
            'PENDING_RENEW',
        ],
        'autoRenew' => true,
        'autoRenewPeriod' => 12,
        'ns' => [
            'ns1.sandave-test.com',
            'ns02.example.com',
        ],
        'premium' => false,
        'childHosts' => [
            'example.com',
        ],
        'registry' => 'sidn',
        'customer' => 'johndoe',
        'privacyProtect' => true,
        'authcode' => '294759302',
        'languageCode' => 'nl',
        'createdDate' => '2020-08-30T01:02:03Z',
        'updatedDate' => '2020-08-30T01:02:03Z',
        'expiryDate' => '2020-11-30T01:02:03Z',
        'zone' => [
            'id' => 1334,
            'service' => 'BASIC',
            'template' => 'template-01',
            'dnssec' => true,
            'link' => true,
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
    ],
    'contacts_registered_in_database' => [
        [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '1',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
        [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '12',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
    ],
    'contacts_from_remote_domain' => [
        'Registrant:johndoe' => [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '12',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
        'ADMIN:johndoe' => [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '12',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
        'TECH:johndoe' => [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '12',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
        'BILLING:johnydoe' => [
            'organization' => 'Sandwave',
            'first_name' => 'Test',
            'last_name' => 'Account 1',
            'gender' => 'x',
            'phone' => '+31635370280',
            'email' => 'jesse@sandwave.io',
            'address' => [
                'street' => 'Testadres',
                'number' => '12',
                'zipcode' => '1001MH',
                'city' => 'Amsterdam',
                'country' => 'NL',
            ],
        ],
    ],
];
