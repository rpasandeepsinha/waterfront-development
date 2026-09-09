<?php

return [
    'singular'   => 'Product staffel',
    'plural'     => 'Product staffels',

    'attributes' => [
        'name'           => 'Naam',
        'description'    => 'Beschrijving',
    ],

    'relations' => [
        'customers' => 'Klanten',
        'product' => 'Abonnement product',
    ],

    'help' => [
        'product' => '(Optioneel) Maakt een nieuw abonnement aan met dit product wanneer een klant aan de product staffel wordt toegevoegd. Bedoeld voor gebruik met de reseller korting producten.',
    ],
];
