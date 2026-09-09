<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Events;

readonly class Microsoft365Webhook
{
    public function __construct(private string $log)
    {
    }

    public function getLog(): string
    {
        return $this->log;
    }
}
