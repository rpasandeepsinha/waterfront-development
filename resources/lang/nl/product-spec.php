<?php

return [
    'singular'   => 'Productspec',
    'plural'     => 'Productspecs',
    'attributes' => [
        'name'  => 'Naam',
        'value' => 'Waarde',
    ],
    'domain' => [
        'allow_whois' => 'Sta whois aanpassen toe',
        'allow_whois_private' => 'Sta anonieme whois toe (yes/support/no)',
    ],
    'hosting' => [
        'services' => [
            'redirecting'       => 'Doorstuurservice',
            'spam_filter'      => 'E-mail met spam filter',
            'outgoing_email'   => 'Uitgaande e-mail',
            'vps'              => 'Virtual Private Server',
        ],
        'permissions' => [
            'manage_crontab'                               => 'Crontab managen',
            'ext_permission_acronis_backup_acronis_backup' => 'Acronis Backups',
        ],
        'php-settings' => [
            'memory_limit' => 'PHP memory limit',
        ],
        'limits'          => [
            'disk_space'  => 'Schijfruimte (in bytes)',
            'max_traffic' => 'Dataverkeer (in bytes)',
            'max_box'     => 'Aantal e-mailadressen',
            'max_db'      => 'Aantal databases',
            'virtual_hosts' => 'Virtual Hosts',
        ],
    ],
    'relations' => [
        'product' => 'Product',
    ],
    'ssl' => [
        'product_id' => 'SSL Product id (https://yh-jira.atlassian.net/wiki/spaces/IN/pages/1204945463/Xolphin+Products)',
    ],
    'vps' => [
        'memory' => 'Intern geheugen',
        'storage' => 'Opslag',
        'cpu' => 'CPU Cores',
    ]
];
