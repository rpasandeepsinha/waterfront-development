<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailSetCatchAll;

use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait;

    private string $domain;

    private string $destinationEmailAddress;

    /**
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'domain',
            'destinationEmailAddress',
        ];
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getDestinationEmailAddress(): string
    {
        return $this->destinationEmailAddress;
    }

    public function setDestinationEmailAddress(string $destinationEmailAddress): void
    {
        $this->destinationEmailAddress = $destinationEmailAddress;
    }
}
