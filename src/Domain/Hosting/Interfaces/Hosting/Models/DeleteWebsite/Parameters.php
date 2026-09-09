<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteWebsite;

use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait;

    private string $domain;

    /**
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'domain',
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
}
