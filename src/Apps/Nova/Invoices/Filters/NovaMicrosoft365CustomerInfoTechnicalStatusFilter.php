<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMicrosoft365CustomerInfoTechnicalStatusFilter extends Filter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('technical_status', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('microsoft365-customer-info.technical-status.initiated') => Microsoft365ProcessStatus::INITIATED->value,
            $this->translator->translate('microsoft365-customer-info.technical-status.customer-created') => Microsoft365ProcessStatus::CUSTOMER_CREATED->value,
            $this->translator->translate('microsoft365-customer-info.technical-status.active') => Microsoft365ProcessStatus::ACTIVE->value,
            $this->translator->translate('microsoft365-customer-info.technical-status.failed') => Microsoft365ProcessStatus::FAILED->value,
        ];
    }
}
