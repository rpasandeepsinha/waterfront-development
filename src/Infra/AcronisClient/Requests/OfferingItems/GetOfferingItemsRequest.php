<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Requests\OfferingItems;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems\OfferingItemsFilter;

class GetOfferingItemsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $tenantId,
        private readonly OfferingItemsFilter $filter,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return sprintf('/tenants/%s/offering_items', $this->tenantId);
    }

    protected function defaultQuery(): array
    {
        $query = [
            'edition' => $this->filter->edition,
        ];

        if ($this->filter->forUi !== null) {
            $query['for_ui'] = $this->filter->forUi;
        }

        if ($this->filter->usageNames !== null && $this->filter->usageNames !== []) {
            $query['usage_names'] = implode(',', $this->filter->usageNames);
        }

        if ($this->filter->availableOnly !== null) {
            $query['available_only'] = $this->filter->availableOnly;
        }

        if ($this->filter->type !== null) {
            $query['type'] = $this->filter->type->value;
        }

        if ($this->filter->status !== null) {
            $query['status'] = $this->filter->status->value;
        }

        if ($this->filter->infraUuid !== null) {
            $query['infra_uuid'] = $this->filter->infraUuid;
        }

        if ($this->filter->includeSecondary !== null) {
            $query['include_secondary'] = $this->filter->includeSecondary;
        }

        return $query;
    }
}
