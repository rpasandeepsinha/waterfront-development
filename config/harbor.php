<?php

declare(strict_types=1);

use Illuminate\Support\Env;

return [
    'host' => Env::get('HARBOR_MESSAGES_HOST', '127.0.0.1'),
    'port' => Env::get('HARBOR_MESSAGES_PORT', 5672),
    'user' => Env::get('HARBOR_MESSAGES_USER', 'guest'),
    'password' => Env::get('HARBOR_MESSAGES_PASSWORD', 'guest'),
    'vhost' => Env::get('HARBOR_MESSAGES_VHOST', '/'),
    'queue_incoming' => Env::get('HARBOR_QUEUE_INCOMING', 'messages_harbor_incoming'),
    'queue_outgoing' => Env::get('HARBOR_QUEUE_OUTGOING', 'messages_harbor_outgoing'),
    'outgoing_consumer_tag' => Env::get('HARBOR_OUTGOING_CONSUMER_TAG', 'waterfront_harbor_consume'),
    'exchange' => Env::get('HARBOR_EXCHANGE', 'messages'),
    'enabled' => Env::get('HARBOR_ENABLED', true),
    'consume_max_per_run' => Env::get('HARBOR_CONSUME_MAX_PER_RUN', 5),
];
