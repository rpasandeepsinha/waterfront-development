<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Orders\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaOrderStatusFilter extends Filter
{
    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('status', $value);
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        $translator = resolve(TranslatorInterface::class);
        return [
            $translator->translate('orders.order_status.on_hold') => OrderStatus::ON_HOLD->value,
            $translator->translate('orders.order_status.in_progress') => OrderStatus::IN_PROGRESS->value,
            $translator->translate('orders.order_status.processed') => OrderStatus::PROCESSED->value,
            $translator->translate('orders.order_status.abuse') => OrderStatus::ABUSE->value,
        ];
    }
}
