<?php

return  [
    'singular' => 'Abonnement',
    'plural'   => 'Abonnementen',

    'type' => [
        'extension' => 'domeinnaam',
        'hosting'   => 'hosting',
        'ssl'       => 'ssl',
        'other'     => 'overige',
        'cloudstack-manager-domain'     => 'cloudstack-manager-domain',
    ],

    'attributes' => [
        'domain'                => 'Domein',
        'product_name'          => 'Productnaam',
        'administrative_status' => 'Administratieve status',
        'technical_status'      => 'Technische Status',
        'period'                => 'Periode',
        'period_name'           => 'Maanden',
        'net_price'             => 'Netto prijs',
        'gross_price'           => 'Bruto prijs',
        'start_date'            => 'Startdatum',
        'end_date'              => 'Einddatum',
        'product_group_name'    => 'Productgroep',
        'internal_comment'      => 'Interne notitie',
        'domainprovider'        => 'Domain Provider',
        'hostingprovider'       => 'Hosting Provider',
        'sslprovider'           => 'SSL provider',
    ],

    'administrative_statuses' => [
        'active'   => 'Actief',
        'canceled' => 'Opgezegd',
        'archived'  => 'Gearchiveerd',
    ],

    'relations' => [
        'customer'      => 'Klant',
        'product_group' => 'Productgroep',
        'product'       => 'Product',
    ],

    'info' => [
        'period'     => 'Looptijd',
        'renew_date' => 'Verlengdatum',
        'price'      => 'Prijs',
    ],

    'hosting-subscription' => [
        'internal_subscription'  => 'Abonnement',
        'server'                 => 'Gekozen server',
        'username'               => 'Plesk gebruikersnaam',
        'customer_id'            => 'Plesk klant id',
        'has_valid_sso_call'     => 'Sso available',
        'unique_server_username' => 'De server en gebruikersnaam moeten uniek zijn.',
    ],

    'ssl-subscription' => [
        'internal_subscription' => 'Abonnement',
        'status' => 'Status',
        'dns_record' => 'Laatste response heeft DNS record',
        'certificate_id' => 'Certificaat id',
        'last_result' => 'Laatste response',
        'last_result_received' => 'Laatste response tijdstip',
        'has_certificates' => 'Certificaat in storage?',
        'has_private' => 'Private key in storage?',
        'webhook_request_received' => 'Laatste webhook tijdstip',
        'webhook_request' => 'Webhook request',
        'request_id' => 'Request id',
    ],

    'domain-subscription' => [
        'internal_subscription' => 'Abonnement',
        'last_result' => 'Laatste response',
        'last_result_received' => 'Laatste response tijdstip',
    ],

    'confirmation' => [
        'message' => 'Het DNS abonnement is aangemaakt',
    ],

    'total' => 'Totaal',
];
