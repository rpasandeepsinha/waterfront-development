<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use DateTimeInterface;

/**
 * @see https://github.com/apache/cloudstack/blob/4.15/api/src/main/java/com/cloud/event/EventTypes.java
 */
class Event
{
    public const STATE_COMPLETED = 'Completed';

    public function __construct(
        public string $id,
        public string $account,
        public string $domainId,
        public string $type,
        public string $description,
        public string $state,
        public string $level,
        public DateTimeInterface $created,
    ) {
    }
}
