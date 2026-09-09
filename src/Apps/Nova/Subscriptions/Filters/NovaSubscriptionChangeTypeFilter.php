<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionChangeTypeFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions-change.type');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('type', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('subscription-change.type.upgrade') => ProductChangeType::UPGRADE->value,
            $this->translator->translate('subscription-change.type.downgrade') => ProductChangeType::DOWNGRADE->value,
        ];
    }
}
