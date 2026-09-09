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

class NovaFailedSubscriptionsFilter extends Filter
{
    public const string TECHNICAL_STATUS = 'technical_status';
    public const string ADMINISTRATIVE_STATUS = 'administrative_status';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.subscriptions.failed-subscriptions');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === self::TECHNICAL_STATUS) {
            $query->whereIn(self::TECHNICAL_STATUS, [
                TechnicalStatus::ERROR->value,
                TechnicalStatus::REGISTRATION->value,
                DomainStatus::FAILED->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::DELETING_FAILED->value,
                TechnicalStatus::SUSPENSION_FAILED->value,
                TechnicalStatus::UNSUSPENSION_FAILED->value,
            ])->whereNotIn(self::ADMINISTRATIVE_STATUS, [
                AdministrativeStatus::CANCELED->value,
                AdministrativeStatus::ARCHIVING->value,
                ...AdministrativeStatus::administrativelyEnded(),
            ]);
        }

        return $query;
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            self::TECHNICAL_STATUS => self::TECHNICAL_STATUS,
        ];
    }
}
