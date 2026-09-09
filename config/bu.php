<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'name' => Env::get('BU_NAME'),
    'first_name' => Env::get('BU_FIRST_NAME'),
    'last_name' => Env::get('BU_LAST_NAME'),
    'department' => Env::get('BU_DEPARTMENT'),
    'city' => Env::get('BU_CITY'),
    'province' => Env::get('BU_PROVINCE'),
    'country_code' => Env::get('BU_COUNTRY_CODE'),
    'street_name' => Env::get('BU_STREET_NAME'),
    'street_number' => Env::get('BU_STREET_NUMBER'),
    'zip_code' => Env::get('BU_ZIP_CODE'),
    'phone_number' => Env::get('BU_PHONE_NUMBER'),
    'approver_email' => Env::get('BU_APPROVER_EMAIL'),
    'support_email' => Env::get('BU_SUPPORT_EMAIL'),
];
