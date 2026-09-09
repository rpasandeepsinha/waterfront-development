<?php

return [
    'singular' => 'Product',
    'plural'   => 'Producten',

    'attributes' => [
        'name'        => 'Naam',
        'slug'        => 'Slug',
        'slug-helper' => 'Let op dat voor hosting de slug gelijk moet zijn aan de paketten op de hosting omgeving (bv Plesk of Directadmin).',
        'description' => 'Omschrijving',
        'orderable'   => 'In bestelflow',
        'weight'      => 'Volgorde',
    ],

    'relations' => [
        'product_group' => 'Productgroep',
    ],
];
