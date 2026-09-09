<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Infra\Translation\TranslatorInterface;

class DomainSubscriptionBusinessUnitFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('domain-business-unit.plural');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->whereHas('businessUnit', fn (Builder $businessUnit) => $businessUnit->where('slug', $value));
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return DomainProviderBusinessUnit::query()
            ->has('domainDeployments')
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn (DomainProviderBusinessUnit $businessUnit) => [
                'label' => $businessUnit->name,
                'value' => $businessUnit->slug,
            ])
            ->toArray();
    }
}
