<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Provision\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use ValueError;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaProvisionRequestTypeFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('provisioning-request.attributes.type');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        try {
            $filter = ProvisionType::from($value);
        } catch (ValueError) {
            return $query;
        }

        return $query->where('request_name', $filter->value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return ProvisionType::cases();
    }
}
