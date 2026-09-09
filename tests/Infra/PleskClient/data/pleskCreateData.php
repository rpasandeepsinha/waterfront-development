<?php

declare(strict_types=1);

return [
    'contactPersonName' => 'Test',
    'emailAddress'      => 'test@email.com',
    'username'          => 'testuser',
    'password'          => 'testpassword',
    'domain'            => 'test-domain.com',
    'ipv4Address'       => '192.0.2.123',
    'customerId'        => '1234',
    'phpVersion'        => 'php13.137-vpn',
    'specs'             => [
        [
            'name'  => 'hosting.limits.max_traffic',
            'value' => 26_843_545_600,
        ],
        [
            'name'  => 'hosting.limits.disk_space',
            'value' => 2_684_354_560,
        ],
        [
            'name'  => 'hosting.limits.max_box',
            'value' => 25,
        ],
        [
            'name'  => 'hosting.limits.advised_box_size',
            'value' => 2_147_483_648,
        ],
        [
            'name'  => 'hosting.limits.max_box_size',
            'value' => 2_684_354_560,
        ],
        [
            'name'  => 'hosting.limits.max_db',
            'value' => 5,
        ],
        [
            'name'  => 'hosting.permissions.manage_crontab',
            'value' => 0,
        ],
        [
            'name'  => 'hosting.php-settings.memory_limit',
            'value' => '128M',
        ],
    ],
    'enableDns'             => 'OFF',
    'enableSsh'             => 'OFF',
    'enableSsl'             => 'OFF',
    'notify'                => 'yes',
    'package'               => 'standard',
];
