<?php

return [
    'singular' => 'Taal',
    'plural'   => 'Talen',

    'attributes' => [
        'locale'    => 'Locale',
        'name'      => 'Naam',
        'active'    => 'Actief',
        'default'   => 'Standaardtaal',
    ],

    'locales' => [
        'nl-NL' => 'Nederlands',
        'en-US' => 'Engels',
    ],

    'translated_percent' => 'Percentage vertaald',
    'activated'          => 'Geactiveerd',

    'nova_info' => [
        'translation_overview'    =>
            'Dit is het talenoverzicht. Hier kun je nieuwe talen toevoegen en instellen of ' .
            'een taal de standaardtaal moet worden voor alle systemen. De gebruiker kan ' .
            'uiteraard altijd zelf een eigen taalvoorkeur instellen.',
        'translationkey_overview'    =>
            'Hier staan alle in onze systemen bekende vertalingen. De bron geeft aan in welke applicatie ' .
            'deze gebruikt wordt. Een vertaalsleutel is de basis voor een vertaling naar een andere taal.',
        'translationstring_overview'    =>
            'Hier vindt je alle vertalingen voor alle talen. Wil je één taal tegelijk zien, of wil je zien ' .
            'voor welke vertaalsleutels er nog geen vertaling is? Maak dan slim gebruik van de filters ' .
            '(het trechter icoontje).',
    ],
];
