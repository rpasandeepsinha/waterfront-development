<?php

declare(strict_types=1);

use SandwaveIo\HarborMessages\Message\DebtorSsoUrl;

return [
    'version' => '1.0',
    'object' => DebtorSsoUrl::class,
    'data' => [
        'customer_number' => 123,
        'debtor_sso_url' => 'http://newurlgoeshere.dev/',
        'admin_url' => 'http://newurlgoeshere.dev/',
    ],
];
