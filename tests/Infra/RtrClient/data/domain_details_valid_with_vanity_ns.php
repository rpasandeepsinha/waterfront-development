<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

$adminContact = include __DIR__ . '/domain_contact_valid.php';
$techContact = include __DIR__ . '/domain_contact_valid.php';
$techContact['role'] = 'TECH';

/** @var string[] $vanityTlds */
$vanityTlds = [
    Config::get('dns.gandi.vanity_nameservers.ns1'),
    Config::get('dns.gandi.vanity_nameservers.ns2'),
    Config::get('dns.gandi.vanity_nameservers.ns3'),
];

return [
    'domainName' => 'example.nl',
    'registry' => 'sidn',
    'customer' => 'johndoe',
    'registrant' => 'johndoe',
    'privacyProtect' => true,
    'status' => ['PENDING_RENEW'],
    'authcode' => '294759302',
    'languageCode' => 'nl',
    'autoRenew' => true,
    'autoRenewPeriod' => 12,
    'ns' => [
        sprintf('ns1.%s', $vanityTlds[0]),
        sprintf('ns2.%s', $vanityTlds[1]),
        sprintf('ns3.%s', $vanityTlds[2]),
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
        $adminContact,
        $techContact,
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
