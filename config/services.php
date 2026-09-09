<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    //3rd party software services
    'ses' => [
        'key'    => Env::get('AWS_ACCESS_KEY_ID'),
        'secret' => Env::get('AWS_SECRET_ACCESS_KEY'),
        'region' => Env::get('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'hubspot' => [
        'access_token' => Env::get('HUBSPOT_API_ACCESS_TOKEN', ''),
        'subscription_object_type_id' => Env::get('HUBSPOT_SUBSCRIPTION_OBJECT_TYPE_ID', ''),
        'subscription_contact_id' => Env::get('HUBSPOT_SUBSCRIPTION_CONTACT_ID', ''),
    ],
];
