<?php

return [
    'singular' => 'Statistiek',
    'plural'   => 'Statistieken',

    'attributes' => [
        'discounted_price'             => 'Korting bedrag',
        'voucher_price'                => 'Voucher bedrag',
        'which_discount'               => 'Welke korting',
        'discount'                     => 'Korting',
        'voucher'                      => 'Voucher',
        'created_at'                   => 'Aangemaakt op',
        'product_name'                 => 'Productnaam',
        'organization'                 => 'Bedrijfsnaam',
        'first_name'                   => 'Voornaam',
        'last_name'                    => 'Achternaam',
        'email'                        => 'E-mailadres',
        'domain'                       => 'Domein',
    ],

    'relations' => [
        'customer'      => 'Klant',
    ],
];
