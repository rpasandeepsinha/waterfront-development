<?php

return [
    'singular'   => 'Api client',
    'plural'     => 'Api clients',
    'attributes' => [
        'name'   => 'Naam',
        'secret' => 'Geheime sleutel',
    ],

    'relations' => [
        'user' => 'Gebruiker',
    ],
];
