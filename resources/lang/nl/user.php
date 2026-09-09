<?php

return [
    'singular'   => 'Gebruiker',
    'plural'     => 'Gebruikers',
    'attributes' => [
        'email'                     => 'E-mailadres',
        'password'                  => 'Wachtwoord',
        'name'                      => 'Naam',
        'first_name'                => 'Voornaam',
        'last_name'                 => 'Achternaam',
        'gender'                    => 'Geslacht',
        'role'                      => 'Rol',
        'status'                    => 'Status',
        'language'                  => 'Taal',
        'image'                     => 'Afbeelding',
        'created_at'                => 'Aangemaakt op',
        'two_auth'                  => 'Twee-factor-authenticatie',
        '2fa_enabled'               => '2FA',
        'password_confirmation'     => 'Wachtwoord bevestigen',
        'current_password'          => 'Huidige wachtwoord',
        'new_password'              => 'Nieuw wachtwoord',
        'new_password_confirmation' => 'Nieuw wachtwoord bevestigen',
    ],

    'relations' => [
        'customer'    => 'Klant',
        'permissions' => 'Permissies',
        'roles'       => 'Rollen',
        'api_clients' => 'Api clients',
    ],

    'heading' => [
        'general'         => 'Algemeen',
        'change-password' => 'Wachtwoord wijzigen',
    ],

    'info' => [
        'change-password' => 'Onderstaande velden zijn enkel verplicht indien u, uw wachtwoord wenst te wijzigen.',
    ],

    'error' => [
        'email-does-not-exist' => 'Ongeldige inloggegevens.',
    ],
];
