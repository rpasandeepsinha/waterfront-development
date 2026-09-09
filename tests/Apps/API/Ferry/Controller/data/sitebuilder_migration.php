<?php

declare(strict_types=1);

return [
    [
        'reference_subscription_id' => 'sub_1337_1',
        'bundle' => [
            'mail_only' => [
                'hostname' => 'mail_server.directadmin.test',
                'driver' => 'directadmin',
                'server_data' => [
                    'directadmin_customer_name' => 'da_mail_1230',
                ],
            ],
            'sitebuilder' => [
                'hostname' => 'basekit.test',
                'driver' => 'basekit',
                'server_data' => [
                    'basekit_user_ref' => 123,
                    'basekit_site_ref' => 456,
                ],
            ],
        ],
    ],
    [
        'reference_subscription_id' => 'sub_1337_2',
        'bundle' => [
            'mail_only' => [
                'hostname' => 'mail_server.plesk.test',
                'driver' => 'integratedservice',
                'server_data' => [
                    'plesk_customer_username' => 'plesk_mail_1230',
                ],
            ],
            'sitebuilder' => [
                'hostname' => 'basekit.test',
                'driver' => 'basekit',
                'server_data' => [
                    'basekit_user_ref' => 789,
                    'basekit_site_ref' => 777,
                ],
            ],
        ],
    ],
];
