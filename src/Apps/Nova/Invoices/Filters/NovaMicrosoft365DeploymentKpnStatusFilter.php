<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMicrosoft365DeploymentKpnStatusFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('kpn_status', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('microsoft365-subscriptions.kpn-status.placed') =>
                Microsoft365OrderStatus::PLACED->value,
            $this->translator->translate('microsoft365-subscriptions.kpn-status.accepted') =>
                Microsoft365OrderStatus::ACCEPTED->value,
            $this->translator->translate('microsoft365-subscriptions.kpn-status.modify-pending') =>
                Microsoft365OrderStatus::MODIFY_PENDING->value,
            $this->translator->translate('microsoft365-subscriptions.kpn-status.modified') =>
                Microsoft365OrderStatus::MODIFIED->value,
            $this->translator->translate('microsoft365-subscriptions.kpn-status.active') =>
                Microsoft365OrderStatus::ACTIVE->value,
            $this->translator->translate('microsoft365-subscriptions.kpn-status.terminated') =>
                Microsoft365OrderStatus::TERMINATED->value,
        ];
    }
}
