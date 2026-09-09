<?php

declare(strict_types=1);

return [
    [
        'hostname' => '204.directadmin.test',
        'reference_subscription_id' => 'sub_1337_1',
        'driver' => 'directadmin_invalid',
        'server_data' => [
            'directadmin_customer_name' => 'da1230',
        ],
    ],
    [
        'hostname' => '204.plesk.test',
        'reference_subscription_id' => 'sub_1337_2',
        'driver' => 'integratedservice_invalid',
        'server_data' => [
            'plesk_customer_username' => 'plesk1230',
            'plesk_customer_id' => 1234,
        ],
    ],
];
