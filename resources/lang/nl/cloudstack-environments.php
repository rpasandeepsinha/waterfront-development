<?php

return [
    'singular'   => 'CloudStack Environment',
    'plural'     => 'CloudStack Environments',

    'attributes' => [
        'name' => 'Naam',
        'slug' => 'Slug',
        'api_url' => 'API URL',
        'ui_url' => 'UI URL',
        'secret_key' => 'Secret key',
        'domain_id' => 'Domain id',
        'domain_name' => 'Domain name',
        'default_email_address' => 'Default email adres',
        'default_role_id' => 'Default role id',
        'preferred' => 'Voorkeur',
    ],

    'attributes_help' => [
        'slug' => 'Wordt gebruikt als onderdeel van de bijbehorende environment variables',
        'domain_id' => 'Beheer CloudStack objecten alleen binnen dit domein',
        'domain_name' => 'Beheer CloudStack objecten alleen binnen dit domein',
        'default_email_address' => 'Standaard email adres van nieuwe klant accounts',
        'default_role_id' => 'Standaard rol van nieuwe klant accounts',
        'preferred' => 'Gebruik als voorkeur environment bij aanmaken nieuwe manager domains',
    ],
];
