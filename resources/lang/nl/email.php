<?php

return [
    'personalised_greetings' => 'Beste :firstName,',
    'activate_account_text'  => 'Klik hier om je account te activeren',
    'reset_password_text'    => 'Klik hier om je wachtwoord opnieuw in te stellen',

    'addon_created_text' => 'Addon :addonName is toegevoegd aan :subscriptionType abonnement voor :domainName.',

    'subscription_cancelled_admin' => [
        'customer_number'    => 'Klantnummer',
        'company_name'       => 'Bedrijfsnaam',
        'user'               => 'Gebruiker (e-mailadres)',
        'domain'             => 'Domeinnaam',
        'service'            => 'Dienst',
        'cancel_option'      => 'Keuze',
        'cancel_end_date'    => 'Stoppen per einde looptijd',
        'transfer'           => 'Wegverhuizen',
        'ip_address'         => 'IP-adres',
    ],

    'subscription_canceled' => [
        'domain'             => 'Domeinnaam',
        'service'            => 'Dienst',
        'cancel_option'      => 'Opzegging',
        'end_date'           => 'Einddatum',
        'updated_at'         => 'Gewijzigd op',
        'cancel_end_date'    => 'per einde looptijd',
        'auth_code'          => 'Wegverhuizen authorisatie code',
        'transfer'           => 'Wegverhuizen',
        'ip_address'         => 'IP-adres',
    ],

    'subscription-cancel-reverted' => [
        'domain'         => 'Domeinnaam',
        'service'        => 'Hostingpakket voor',
        'cancel_option'  => 'Opzegging',
        'end_date'       => 'Einddatum',
        'updated_at'     => 'Gewijzigd op',
        'body'           => 'De opheffing van het volgende product is ingetrokken:',
        'extend_product' => 'Bovenstaande producten zullen per :end_date voor :contract_period worden verlengd.',
    ],

    'send_plesk_details' => [
        'hostname' => 'Hostname:',
        'username' => 'Gebruikersnaam:',
        'password' => 'Wachtwoord:',
        'ftp'      => 'ftp.:domainName',
        'or'       => 'of',
    ],

    'directadmin_details' => [
        'hostname' => 'Hostname:',
        'username' => 'Gebruikersnaam:',
        'password' => 'Wachtwoord:',
        'ftp'      => 'ftp.:domainName',
        'or'       => 'of',
    ],

    'send_cloudstack_manager_domain_details' => [
        'username' => 'Gebruikersnaam:',
        'password' => 'Wachtwoord:',
        'domain'   => 'Domein:',
    ],

    'send_cloudstack_manager_vps_details' => [
        'username'   => 'Gebruikersnaam:',
        'password'   => 'Wachtwoord:',
        'ssh-keys'   => 'Om in te loggen op je VPS moeten je SSH Key Pairs gekoppeld worden. Dit kun je doen door vanuit je klantenpaneel je VPS te configureren in CloudStack.',
        'ipaddress'  => 'IPv4-adres:',
        'ip6address' => 'IPv6-adres:',
    ],

    'upgrade-product' => [
        'header'  => 'Product is ge-upgrade',
        'subject' => 'Product is ge-upgrade',
    ],

    'downgrade-product' => [
        'header'  => 'Product is ge-downgrade',
        'subject' => 'Product is ge-downgrade',
    ],

    'directadmin' => [
        'header'  => 'DirectAdmin hosting gegevens',
        'subject' => 'DirectAdmin Reseller hosting pakket is succesvol besteld',
    ],

    'hosting-upgrade' => [
        'title'   => 'Hosting upgrade',
        'subject' => 'Hosting upgrade succesvol!',
        'header'  => 'Hosting upgrade succesvol!',
    ],

    'transfer-status' => [
        'subject-created-sender'   => 'Transfer is aangemaakt',
        'header-created-sender'    => 'Transfer is aangemaakt',
        'body-created-sender'      => 'Er is een bericht naar de andere gebruiker verstuurd om hem op de hoogte te brengen.',
        'subject-created-receiver' => 'Een gebruiker wil een product naar u transferen',
        'header-created-receiver'  => 'Inkomende transfer',
        'body-created-receiver'    => 'U heeft een of meerdere openstaande transfers. Ga naar uw gebruikerspaneel om het te accepteren of te weigeren.',

        'subject-accepted-sender'   => 'Transfer is geaccepteerd',
        'header-accepted-sender'    => 'Transfer is geaccepteerd',
        'body-accepted-sender'      => 'De transfer is geaccepteerd door de andere gebruiker en zal nu verwerkt worden.',
        'subject-accepted-receiver' => 'Transfer is geaccepteerd',
        'header-accepted-receiver'  => 'Transfer is geaccepteerd',
        'body-accepted-receiver'    => 'De transfer is succesvol geaccepteerd en zal nu verwerkt worden.',

        'subject-started-sender'   => 'Transfer is begonnen',
        'header-started-sender'    => 'Transfer begonnen',
        'body-started-sender'      => 'De transfer is begonnen. Op de transfer pagina kunt u de actuele status van de transfer bekijken.',
        'subject-started-receiver' => 'Transfer is begonnen',
        'header-started-receiver'  => 'Transfer begonnen',
        'body-started-receiver'    => 'De geaccepteerde transfer is gestart. Op de transfer pagina kunt u de actuele status van de transfer bekijken.',

        'subject-canceled-sender'   => 'Transfer is succesvol geannuleerd',
        'header-canceled-sender'    => 'Transfer geannuleerd',
        'body-canceled-sender'      => 'De transfer is succesvol geannuleerd.',
        'subject-canceled-receiver' => 'De transfer is geannuleerd',
        'header-canceled-receiver'  => 'Transfer geannuleerd',
        'body-canceled-receiver'    => 'De andere gebruiker heeft de transfer geannuleerd.',

        'subject-completed-sender'   => 'Transfer is voltooid',
        'header-completed-sender'    => 'Transfer is voltooid',
        'body-completed-sender'      => 'De transfer is voltooid.',
        'subject-completed-receiver' => 'Transfer is voltooid',
        'header-completed-receiver'  => 'Transfer is voltooid',
        'body-completed-receiver'    => 'De transfer is voltooid. De nieuwe subscriptie kunt u terugvinden in het gebruikerspaneel. Zorg ervoor dat u eventuele wachtwoorden (bij servers bijvoorbeeld) gelijk veranderd!',

        'subject-rejected-sender'   => 'Transfer is geweigerd',
        'header-rejected-sender'    => 'Transfer is geweigerd',
        'body-rejected-sender'      => 'De transfer is geweigerd door de andere gebruiker.',
        'subject-rejected-receiver' => 'Transfer geweigerd',
        'header-rejected-receiver'  => 'Transfer geweigerd',
        'body-rejected-receiver'    => 'De transfer is succesvol geweigerd.',

        'subject-failed-sender'   => 'Transfer is gefaald',
        'header-failed-sender'    => 'Transfer is gefaald',
        'body-failed-sender'      => 'De transfer is gefaald. Kijk op de transfer pagina voor meer informatie of neem contact op met support.',
        'subject-failed-receiver' => 'Transfer is gefaald',
        'header-failed-receiver'  => 'Transfer is gefaald',
        'body-failed-receiver'    => 'De transfer is gefaald. Kijk op de transfer pagina voor meer informatie of neem contact op met support.',
    ],

    'product' => 'Product',
    'years'   => 'jaar',
    'months'  => 'maand',

    'sitebuilder' => [
        'subject' => 'Sitebuilder aangemaakt',
        'header'  => 'Sitebuilder aangemaakt',
        'body'    => 'Met de sitebuilder voor :domainName kun je direct aan de slag om je website te ontwerpen. '
            . ' Wil je ook kunnen mailen met je eigen domeinnaam? Je maakt ze aan via het hostingpakket.',
    ],
];
