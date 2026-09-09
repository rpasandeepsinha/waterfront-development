<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'imap_host' => Env::get('MAIL_ONLY_IMAP_HOST'),
    'imap_port' => Env::get('MAIL_ONLY_IMAP_PORT', 993),
    'imap_encryption' => Env::get('MAIL_ONLY_IMAP_ENCRYPTION', 'SSL'),

    'pop3_host' => Env::get('MAIL_ONLY_POP3_HOST'),
    'pop3_port' => Env::get('MAIL_ONLY_POP3_PORT', 995),
    'pop3_encryption' => Env::get('MAIL_ONLY_POP3_ENCRYPTION', 'SSL'),

    'smtp_host' => Env::get('MAIL_ONLY_SMTP_HOST'),
    'smtp_port' => Env::get('MAIL_ONLY_SMTP_PORT', 465),
    'smtp_encryption' => Env::get('MAIL_ONLY_SMTP_ENCRYPTION', 'SSL'),

    'dns' => [
        [
            'type' => Env::get('MAIL_ONLY_DNS_1_TYPE', 'MX'),
            'name' => Env::get('MAIL_ONLY_DNS_1_NAME', ''),
            'value' => Env::get('MAIL_ONLY_DNS_1_VALUE'),
            'prio' => Env::get('MAIL_ONLY_DNS_1_PRIO', 10),
        ],
        [
            'type' => Env::get('MAIL_ONLY_DNS_2_TYPE', 'MX'),
            'name' => Env::get('MAIL_ONLY_DNS_2_NAME', ''),
            'value' => Env::get('MAIL_ONLY_DNS_2_VALUE'),
            'prio' => Env::get('MAIL_ONLY_DNS_2_PRIO', 20),
        ],
    ],
];
