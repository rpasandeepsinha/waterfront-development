<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceFilter
{
    /**
     * @param Builder<Invoice> $query
     *
     * @return Builder<Invoice>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $this->applySearch($query, $request);

        return $query;
    }

    /**
     * @param Builder<Invoice> $query
     */
    private function applySearch(Builder $query, Request $request): void
    {
        $search = strtolower($request->string('search')->trim()->toString());

        if ($search === '' || strlen($search) > 50) {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->where('title', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
        });
    }
}
