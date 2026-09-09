<?php

return [
    'singular'   => 'Mail Provider',
    'plural'     => 'Mail Providers',

    'attributes' => [
        'slug'               => 'Slug',
        'driver'             => 'Driver',
        'enabled'            => 'Ingeschakeld',
        'default'            => 'Standaard',
        'connection_details' => 'Connectie informatie',
        'quota'              => "Mail box quota (MB)",
        'limit'              => "Mail verzend limiet",
    ],

    'errors' => [
        'configuration-error' => 'Er is iets misgegaan tijdens het ophalen van de configuratie.',
        'users-error' => 'Er is iets misgegaan tijdens het verwijderen van de gebruiker.',
        'create-user-error' => 'Er is iets misgegaan tijdens het aanmaken van de gebruiker.',
        'delete-user-error' => 'Er is iets misgegaan tijdens het verwijderen van de gebruiker.',
        'password-reset-error' => 'Er is iets misgegaan tijdens het resetten van een wachtwoord.',
        'domain-does-not-exist' => 'Het subscriptie domein is niet gevonden.',
        'username-does-not-exist' => 'De opgegeven gebruikersnaam is niet gevonden.',
        'spamexperts-sso-error'   => 'Er is iets misgegaan tijdens het opvragen van een Spam Experts login link.'
    ],

    'password-min' => 'Gebruik een wachtwoord van minimaal 6 karakters.',
    'password-regex' => 'Gebruik minimaal 1 kleine letter, 1 hoofdletter en 1 cijfer.',
];
