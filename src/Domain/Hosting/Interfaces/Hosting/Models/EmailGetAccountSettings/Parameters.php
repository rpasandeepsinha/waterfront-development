<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings;

use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait;

    private int $siteId;

    /**
     * @return array<string>
     */
    public static function getRequiredFields(): array
    {
        return [
            'siteId',
        ];
    }

    public function setSiteId(int $siteId): void
    {
        $this->siteId = $siteId;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }
}
