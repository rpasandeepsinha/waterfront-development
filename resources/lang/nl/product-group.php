<?php

return [
    'singular'   => 'Productgroep',
    'plural'     => 'Productgroepen',

    'rates'      => 'Ratios',

    'attributes' => [
        'name'           => 'Naam',
        'slug'           => 'Slug',
        'ledger_code' => 'Grootboeknummer',
        'default_rate'   => 'Standaard partnerprogramma tarief (%)',
        'actual_rate'    => 'Actueel partnerprogramma tarief (%)',
    ],

    'relations' => [
        'customers' => 'Korting',
    ],
];
