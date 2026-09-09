<?php

declare(strict_types=1);

return [
    'domainName' => 'example.nl',
    'registry' => 'sidn',
    'customer' => 'testcustomer',
    'registrant' => 'johndoe',
    'privacyProtect' => true,
    'status' => ['PENDING_RENEW'],
    'authcode' => '294759302',
    'languageCode' => 'nl',
    'autoRenew' => true,
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
    'zone' => include __DIR__ . '/zone_valid.php',
    'contacts' => [
        [
            'role' => 'ADMIN',
            'handle' => 'johndoe-admin',
        ],
        [
            'role' => 'TECH',
            'handle' => 'johndoe-tech',
        ],
    ],
    'keyData' => [
        include __DIR__ . '/key_data_valid.php',
        include __DIR__ . '/key_data_valid_2.php',
    ],
    'ds_data' => [
        include __DIR__ . '/ds_data_valid.php',
        include __DIR__ . '/ds_data_valid.php',
    ],
];
