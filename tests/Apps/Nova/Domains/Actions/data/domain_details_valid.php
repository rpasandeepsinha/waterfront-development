<?php

declare(strict_types=1);

$adminContact = include __DIR__ . '/domain_contact_valid.php';
$techContact = include __DIR__ . '/domain_contact_valid.php';
$financialContact = include __DIR__ . '/domain_contact_valid_financial.php';
$techContact['role'] = 'TECH';

return [
    'domainName' => 'example.nl',
    'registrant' => 'johndoe',
    'status' => ['PENDING_RENEW'],
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
    'createdDate' => '2020-08-30 01:02:03',
    'updatedDate' => '2020-08-30 01:02:03',
    'expiryDate' => '2020-11-30 01:02:03',
    'zone' => include __DIR__ . '/zone_valid.php',
    'contacts' => [
        $adminContact,
        $techContact,
        $financialContact,
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
