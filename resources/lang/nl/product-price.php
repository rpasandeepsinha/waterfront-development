<?php

return [
    'singular' => 'Productprijs',
    'plural'   => 'Productprijzen',

    'attributes' => [
        'period'          => 'Periode',
        'type'            => 'Type',
        'regular_price'   => 'Prijs',
        'promotion_price' => 'Actieprijs',
    ],

    'relations' => [
        'product' => 'Product',
        'productDiscount' => 'Product staffel',
    ],

    'info' => [
        'period' => 'Looptijd in maanden',
    ],

    'types' => [
        'registration' => 'Registratie',
        'prolongation' => 'Verlenging',
    ],
];
