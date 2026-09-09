<?php

declare(strict_types=1);

return [
    'commonName' => 'sandwave',
    'requiresAttention' => true,
    'validations' => [
        'organization' => 'VALIDATED',
        'docs' => 'VALIDATED',
        'voice' => 'VALIDATED',
        'whois' => 'VALIDATED',
        'agreement' => 'VALIDATED',
        'dcv' => [[
            'commonName' => 'dcv',
            'type' => 'DNS',
            'status' => 'ATTENTION',
            'caaRecordStatus' => 'EMPTY',
            'dnsRecord' => '_c7fbc2039e400c8ef74129ec7db1842c.example.nl.',
            'dnsType' => 'CNAME',
            'dnsContents' => 'c9c863405fe7675a3988b97664ea6baf.442019e4e52fa335f406f7c5f26cf14f.sectigo.com.',
            'fileLocation' => 'fileLocation',
            'fileContents' => 'fileContents',
            'riskStatus' => 'PASSED',
        ]],
    ],
];
