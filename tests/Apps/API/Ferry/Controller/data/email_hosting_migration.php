<?php

declare(strict_types=1);

return [
    [
        'hostname' => 'mail_server.directadmin.test',
        'reference_subscription_id' => 'sub_1337_1_directadmin_with_mail_only_server_spec',
        'driver' => 'directadmin',
        'server_data' => [
            'directadmin_customer_name' => 'da_mail_1230',
        ],
    ],
    [
        'hostname' => 'mail_server.directadmin.test',
        'reference_subscription_id' => 'sub_1337_1_directadmin_without_mail_only_server_spec',
        'driver' => 'directadmin',
        'server_data' => [
            'directadmin_customer_name' => 'da_mail_1231',
        ],
    ],
    [
        'hostname' => 'mail_server.directadmin.test',
        'reference_subscription_id' => 'reference_subscription_but_is_normal_hosting',
        'driver' => 'directadmin',
        'server_data' => [
            'directadmin_customer_name' => 'da_1230',
        ],
    ],
    [
        'hostname' => 'normal_server.plesk.test',
        'reference_subscription_id' => 'sub_1337_1_plesk_with_mail_only_server_spec',
        'driver' => 'integratedservice',
        'server_data' => [
            'plesk_customer_username' => 'plesk123',
        ],
    ],
    [
        'hostname' => 'normal_server.plesk.test',
        'reference_subscription_id' => 'sub_1337_1_plesk_without_mail_only_server_spec',
        'driver' => 'integratedservice',
        'server_data' => [
            'plesk_customer_username' => 'plesk456',
        ],
    ],
];
