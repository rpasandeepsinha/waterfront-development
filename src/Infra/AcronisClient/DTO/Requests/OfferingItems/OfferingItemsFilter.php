<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Requests\OfferingItems;

use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemType;

class OfferingItemsFilter
{
    /**
     * @param list<string>|null $usageNames
     */
    public function __construct(
        public string $edition = '*',
        public ?bool $forUi = null,
        public ?array $usageNames = null,
        public ?bool $availableOnly = null,
        public ?OfferingItemType $type = null,
        public ?OfferingItemStatus $status = null,
        public ?string $infraUuid = null,
        public ?bool $includeSecondary = null,
    ) {
    }

    /**
     * @return array<string, scalar>
     */
    public function toQuery(): array
    {
        $query = [
            'edition' => $this->edition,
        ];

        if ($this->forUi !== null) {
            $query['for_ui'] = $this->forUi;
        }

        if ($this->usageNames !== null && $this->usageNames !== []) {
            $query['usage_names'] = implode(',', $this->usageNames);
        }

        if ($this->availableOnly !== null) {
            $query['available_only'] = $this->availableOnly;
        }

        if ($this->type !== null) {
            $query['type'] = $this->type->value;
        }

        if ($this->status !== null) {
            $query['status'] = $this->status->value;
        }

        if ($this->infraUuid !== null) {
            $query['infra_uuid'] = $this->infraUuid;
        }

        if ($this->includeSecondary !== null) {
            $query['include_secondary'] = $this->includeSecondary;
        }

        return $query;
    }
}
