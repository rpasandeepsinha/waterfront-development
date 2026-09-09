<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\RtrClient\Services\Enums\LogStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaPendingSubscriptionsFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.pending-subscriptions');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_string($value));
        return $query
            ->whereHas('domainDeployment', function (Builder $q) use ($value) {
                $q->whereHas('domainProviderStatus', function (Builder $q) use ($value) {
                    $q->whereIn('status', LogStatus::getPendingStatuses())
                        ->whereDate('created_at', '<=', CarbonImmutable::now()->subDays((int) $value)->toDateTime());
                });
            });
    }

    /**
     * @return array<string, int>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('general.all') => 0,
            $this->translator->translate('general.older_than_x_days', ['days' => 30]) => 30,
            $this->translator->translate('general.older_than_x_days', ['days' => 60]) => 60,
            $this->translator->translate('general.older_than_x_days', ['days' => 90]) => 90,
        ];
    }
}
