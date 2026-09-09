<?php

return [
    'singular' => 'Server',
    'plural'   => 'Servers',

    'attributes' => [
        'type'                     => 'Type',
        'name'                     => 'Naam',
        'hostname'                 => 'Host',
        'port'                     => 'poort',
        'ipv4'                     => 'ipv4-adres',
        'ipv6'                     => 'ipv6-adres',
        'plesk_version'            => 'Plesk versie',
        'owner'                    => 'Eigenaar (BU Naam)',
        'allow_new_websites'       => 'Websites toevoegbaar',
        'maximum_websites'         => 'Maximaal aantal websites',
        'secret_key'               => 'Geheime sleutel (Secret Key Plesk)',
        'login_key'                => 'Geheime sleutel (Login Key DirectAdmin)',
        'use_ssl'                  => 'Gebruikt beveiligde verbinding (SSL)',
        'customer_login_as_admin'  => 'Klant mag inloggen als beheerder (Alleen Plesk)',

        'username'    => 'Gebruikersnaam',
        'password'    => 'Wachtwoord',
        'php_version' => 'PHP Versie',
    ],

    'relations' => [
        'customer' => 'Klant (Private Server)',
    ],
];
