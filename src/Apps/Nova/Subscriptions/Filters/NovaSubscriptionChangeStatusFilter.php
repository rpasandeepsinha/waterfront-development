<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionChangeStatusFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions-change.status');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('status', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('subscription-change.status.requested') => SubscriptionChangeStatus::REQUESTED->value,
            $this->translator->translate('subscription-change.status.in_progress') => SubscriptionChangeStatus::INPROGRESS->value,
            $this->translator->translate('subscription-change.status.completed') => SubscriptionChangeStatus::COMPLETED->value,
        ];
    }
}
