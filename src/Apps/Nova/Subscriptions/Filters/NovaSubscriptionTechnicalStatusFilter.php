<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionTechnicalStatusFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.technical-status');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query
            ->where('technical_status', $value)
            ->whereNot('administrative_status', AdministrativeStatus::ARCHIVED->value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('subscription.technical_statuses.ok') => TechnicalStatus::OK->value,
            $this->translator->translate('subscription.technical_statuses.extension_active') => DomainStatus::ACTIVE->value,
            $this->translator->translate('subscription.technical_statuses.error') => TechnicalStatus::ERROR->value,
            $this->translator->translate('subscription.technical_statuses.failed') => TechnicalStatus::FAILED->value,
            $this->translator->translate('subscription.technical_statuses.registration') => TechnicalStatus::REGISTRATION->value,
            $this->translator->translate('subscription.technical_statuses.deleted') => TechnicalStatus::DELETED->value,
            $this->translator->translate('subscription.technical_statuses.deleting') => TechnicalStatus::DELETING->value,
            $this->translator->translate('subscription.technical_statuses.deleting_failed') => TechnicalStatus::DELETING_FAILED->value,
            $this->translator->translate('subscription.technical_statuses.pending') => TechnicalStatus::PENDING->value,
            $this->translator->translate('subscription.technical_statuses.suspended') => TechnicalStatus::SUSPENDED->value,
            $this->translator->translate('subscription.technical_statuses.failed_suspension') => TechnicalStatus::SUSPENSION_FAILED->value,
            $this->translator->translate('subscription.technical_statuses.failed_unsuspension') => TechnicalStatus::UNSUSPENSION_FAILED->value,
            $this->translator->translate('subscription.technical_statuses.suspending') => TechnicalStatus::SUSPENDING->value,
            $this->translator->translate('subscription.technical_statuses.unsuspending') => TechnicalStatus::UNSUSPENDING->value,
        ];
    }
}
