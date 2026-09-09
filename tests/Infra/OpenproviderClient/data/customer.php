<?php

declare(strict_types=1);

use Waterfront\Domain\Customers\Enums\Gender;

return [
    'id'                      => 2,
    'customer_number'         => 2,
    'organization'            => 'Sandwave',
    'department'              => 'Nautilus',
    'first_name'              => 'Firstname',
    'last_name'               => 'Lastname',
    'gender'                  => Gender::MALE->value,
    'phone_country_code'      => '31',
    'phone_area_code'         => '61',
    'phone_subscriber_number' => '123123123',
    'email'                   => 'support@sandwave.io',
    'locale'                  => 'nl-NL',
    'created_at'              => '2018-12-13 09:21:10',
    'updated_at'              => '2018-12-13 09:21:10',
    'address'                 => [
        'id'            => 1,
        'customer_id'   => 2,
        'street_name'   => 'Australiëlaan',
        'street_number' => '11',
        'zip_code'      => '3526 AB',
        'city'          => 'Utrecht',
        'country_code'  => 'NL',
        'created_at'    => '2018-12-13 09:21:10',
        'updated_at'    => '2018-12-13 09:21:10',
    ],
];
