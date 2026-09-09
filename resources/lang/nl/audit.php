<?php

return [
    'id'             => 'ID Audit',
    'user'           => 'Gebruiker',
    'user_id'        => 'ID Gebruiker',
    'user_name'      => 'Naam Gebruiker',
    'model_event'    => 'Actie',
    'model'          => 'Model',
    'auditable'      => 'Model',
    'auditable_type' => 'Type Model',
    'auditable_id'   => 'ID Model',
    'old_values'     => 'Oude waarden',
    'new_values'     => 'Nieuwe waarden',
    'url'            => 'URL',
    'ip_address'     => 'IP adres',
    'user_agent'     => 'User agent',
    'tags'           => 'Tags',
    'created_at'     => 'Datum',
    'updated_at'     => 'Audit aangepast op',

    'description' => ':Model :event',

    'events' => [
        'created'   => 'Aangemaakt',
        'updated'   => 'Bijgewerkt',
        'deleted'   => 'Verwijderd',
        'loggedin'  => 'Ingelogd',
        'loggedout' => 'Uitgelogd',
        'tfasent'   => 'Verificatiecode verstuurd',
    ],

    'types' => [
        'Customer'        => 'Organisatie',
        'CustomerAddress' => 'Organisatie adres',
        'Subscription'    => 'Abonnement',
        'Template'        => 'Sjabloon',
        'User'            => 'Gebruiker',
    ],
];
