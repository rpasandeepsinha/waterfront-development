<?php

declare(strict_types=1);

use SandwaveIo\HarborMessages\Message\MandateAnnouncement;

return [
    'version' => '1.0',
    'object' => MandateAnnouncement::class,
    'data' => new MandateAnnouncement(
        'mdt_someid123',
        1337,
        true,
    )->toArray(),
];
