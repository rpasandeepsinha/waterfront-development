<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Acronis\Repositories;

use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;

class AcronisProviderRepository
{
    public function find(int $id): ?AcronisProvider
    {
        return AcronisProvider::query()
            ->find($id);
    }

    public function getDefault(): ?AcronisProvider
    {
        return AcronisProvider::query()
            ->where('default', true)
            ->first();
    }
}
