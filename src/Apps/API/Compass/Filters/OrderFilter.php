<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Repositories\OrderRepository;

class OrderFilter
{
    public function __construct(
        private readonly Sorting $sorting,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * @param Builder<Order> $query
     *
     * @return Builder<Order>
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $this->applyStatusFilter($query, $request);
        $this->applyOrderedByFilter($query, $request);
        $this->applyEmployeeEmailSearch($query, $request);

        $this->sorting->apply($query, array_filter((array) $request->input('orderBy', []), 'is_string'));

        return $query;
    }

    /** @param Builder<Order> $query */
    private function applyStatusFilter(Builder $query, Request $request): void
    {
        $values = array_values(array_filter((array) $request->input('status'), 'is_string'));

        if ($values !== []) {
            $query->whereIn('status', $values);
        }
    }

    /** @param Builder<Order> $query */
    private function applyOrderedByFilter(Builder $query, Request $request): void
    {
        if (! $request->has('ordered_by')) {
            return;
        }

        $schemaId = $request->string('ordered_by')->toString();

        if (! in_array($schemaId, ['customer', 'employee'], true)) {
            return;
        }

        $this->orderRepository->whereOrderedByMetadataSchemaId($query, $schemaId);
    }

    /** @param Builder<Order> $query */
    private function applyEmployeeEmailSearch(Builder $query, Request $request): void
    {
        $email = strtolower($request->string('search')->trim()->toString());

        if ($email === '' || strlen($email) > 100) {
            return;
        }

        $this->orderRepository->whereOrderedByMetadataEmailContains($query, $email);
    }
}
