<?php

return [
    'singular' => 'Klant',
    'plural'   => 'Klanten',

    'attributes' => [
        'customer_number'              => 'Klantnummer',
        'organization'                 => 'Bedrijfsnaam',
        'department'                   => 'Afdeling',
        'name'                         => 'Naam',
        'first_name'                   => 'Voornaam',
        'last_name'                    => 'Achternaam',
        'email'                        => 'E-mailadres',
        'phone'                        => 'Telefoonnummer',
        'coc_number'                   => 'KvK-nummer',
        'vat_number'                   => 'BTW-nummer',
        'purchase_reference'           => 'Inkoopkenmerk',
        'invoice_history_url'          => 'URL naar factuurhistorie',
        'terms_of_payment'             => 'Betalingstermijn',
        'credit_limit'                 => 'Kredietlimiet',
        'internal_comment'             => 'Interne notitie',
        'icp'                          => 'ICP',
        'payment_type'                 => 'Betaalwijze',
        'anonymized_at'                => 'Geanonimiseerd op',
        'is-company'                   => 'Bedrijf',
    ],

    'address' => [
        'singular'   => 'Adres',
        'plural'     => 'Adressen',
        'attributes' => [
            'street_name'   => 'Straatnaam',
            'street_number' => 'Huisnummer',
            'zip_code'      => 'Postcode',
            'city'          => 'Plaats',
            'country'       => 'Land',
        ],
    ],

    'contact' => [
        'singular'   => 'Contact',
        'plural'     => 'Contacten',
        'attributes' => [
            'first_name' => 'Voornaam',
            'last_name'  => 'Achternaam',
            'email'      => 'E-mailadres',
        ],
    ],

    'relations' => [
        'product_groups'    => 'Productgroep korting',
        'product_discounts' => 'Product staffel',
        'customers'         => 'Indirecte eindklanten',
        'related_customers' => 'Gerelateerde eindklanten',
        'history'           => 'Klant historie',
    ],

    'discount' => 'Korting (%)',

    'partner' => 'Partner',

    'financial' => 'Financieel',

    'transfers' => 'Overdrachten',

    'info' => [
        'discount' => 'Korting in procenten',
    ],

    'phone-country-error' => 'Incorrect telefoonnummer. Valide voorbeelden: +31 113-643281 | +31 610571442',
    'specialchar-error' => 'Speciale tekens kunnen niet worden gebruikt zoals bijv. :characters',
];
