<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Waterfront\Domain\Products\Models\HostingProductComposition;

class HostingProductCompositionRepository
{
    /** @return Collection<int, HostingProductComposition> */
    public function findAllHostingProductCompositions(): Collection
    {
        return HostingProductComposition::query()->with([
            'composedProduct',
            'wpComposedProduct',
            'mailOnlyProduct',
            'webOnlyProduct',
            'wpWebOnlyProduct',
        ])->get();
    }
}
