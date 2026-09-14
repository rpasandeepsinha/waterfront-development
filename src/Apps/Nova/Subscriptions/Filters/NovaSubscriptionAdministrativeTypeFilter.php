<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionAdministrativeTypeFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.administrative-status');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('administrative_status', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('subscription.administrative_statuses.active') =>
                AdministrativeStatus::ACTIVE->value,
            $this->translator->translate('subscription.administrative_statuses.canceled') =>
                AdministrativeStatus::CANCELED->value,
            $this->translator->translate('subscription.administrative_statuses.archived') =>
                AdministrativeStatus::ARCHIVED->value,
            $this->translator->translate('subscription.administrative_statuses.expired') =>
                AdministrativeStatus::EXPIRED->value,
            $this->translator->translate('subscription.administrative_statuses.inactive') =>
                AdministrativeStatus::INACTIVE->value,
            $this->translator->translate('subscription.administrative_statuses.suspended') =>
                AdministrativeStatus::SUSPENDED->value,
            $this->translator->translate('subscription.administrative_statuses.archiving') =>
                AdministrativeStatus::ARCHIVING->value,
        ];
    }
}
