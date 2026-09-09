<?php

return [
    'singular' => 'Factuurregel',
    'plural'   => 'Factuurregels',

    'attributes' => [
        'domain'          => 'Domein',
        'period'          => 'Periode',
        'start_date'      => 'Startdatum',
        'end_date'        => 'Einddatum',
        'duration'        => 'Looptijd',
        'gross_price'     => 'Prijs (exclusief BTW)',
        'net_price'       => 'Prijs inclusief korting (exclusief BTW)',
        'net_price_inc_vat' => 'Prijs inclusief korting (inclusief BTW)',
        'ledger_code'  => 'Grootboeknummer',
        'sent_to_harbor_at' => 'Naar Harbor verstuurd op',
        'created_at'      => 'Aangemaakt op',
        'paid'            => 'Betaald via Mollie',
        'vat_code'        => 'BTW code',
        'vat_rate'        => 'BTW tarief',
    ],

    'info' => [
        'period' => 'Looptijd in maanden',
    ],
];
