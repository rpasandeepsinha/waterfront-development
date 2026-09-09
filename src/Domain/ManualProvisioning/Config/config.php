<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'notification-receiver-name' => Env::get('MANUAL_PROVISIONING_NOTIFICATION_RECEIVER_NAME', 'Contact Support'),
    'notification-email' => Env::get('MANUAL_PROVISIONING_NOTIFICATION_MAIL'),
];
