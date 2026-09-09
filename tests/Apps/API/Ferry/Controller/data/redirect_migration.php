<?php

declare(strict_types=1);

use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

return [
    [
        'source' => 'subdomain.test-domain.nl',
        'destination' => 'https://google.com',
    ],
    [
        'source' => 'invalid-test-domain.nl',
        'destination' => 'https://google.com',
        'type' => RedirectType::PERMANENT,
    ],
];
