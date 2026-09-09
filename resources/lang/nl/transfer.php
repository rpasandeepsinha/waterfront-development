<?php

return [
    'singular' => 'Overdracht',
    'plural'   => 'Overdrachten',

    'attributes' => [
        'status' => 'Status',
    ],

    'relations' => [
        'from_customer' => 'Van klant',
        'to_customer' => 'Naar klant',
        'subscriptions' => 'Abonnementen',
        'is_in_transfer' => 'In overdracht',
    ],

    'incoming' => 'Inkomende overdrachten',
    'outgoing' => 'Uitgaande overdrachten',

    'customers' => [
        'store' => [
            'subscription_validation' => 'Helaas is het valideren van het abonnement mislukt.',
            'unable_to_resolve_customer' => 'Helaas is het valideren van de ontvangende klant mislukt.',
            'unable_to_resolve_subscriptions'=> 'Helaas is het valideren van het abonnement mislukt.',
        ],
        'accept-success'=> 'Uw overdracht is succesvol geaccepteerd.',
        'accept-failure'=> 'Uw overdracht kon niet worden geaccepteerd.  Neem contact op met support.',
        'cancel' => [
            'transfer_other_customer' => 'Ingeschoten overdracht behoort niet tot huidige klant.',
                'unable_to_resolve_customer' => 'Validatie van de verzendende klant is mislukt.',
                'incorrect_status' => 'De status van de overdracht is incorrect.',
                'success' => 'succes',
        ],
        'reject' => [
            'transfer_other_customer' => 'Ingeschoten overdracht behoort niet tot huidige klant.',
            'unable_to_resolve_customer' => 'Validatie van de ontvangende klant is mislukt.',
            'incorrect_status' => 'De status van de overdracht is incorrect.',
            'success' => 'succes',
        ],
    ],
];
