<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

interface FerryWebhookInterface
{
    public function toFerryString(): string;

    /**
     * @return array<string, int>
     */
    public function toFerryArray(): array;
}
