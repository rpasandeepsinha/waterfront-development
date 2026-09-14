<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Metrics;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaTotalSubscriptionsMetric extends Value
{
    public $icon = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        ?string $component = null,
    ) {
        parent::__construct($component);
    }

    public function calculate(NovaRequest $request): ValueResult
    {
        $subscriptionCount = Subscription::query()
            ->whereNot('administrative_status', AdministrativeStatus::ARCHIVED->value)
            ->count();

        $migratedSubscriptionsCount = MigratedSubscription::query()
            ->whereHas('subscriptions', function (Builder $query) {
                $query->whereNot('administrative_status', AdministrativeStatus::ARCHIVED->value);
            })
            ->count();

        return $this->result($subscriptionCount)
            ->format('0')
            ->suffix($this->translator->translate('nova_dashboard.metrics.from_migrations', [
                'count' => $migratedSubscriptionsCount,
            ]));
    }

    public function name(): string
    {
        return $this->translator->translate('subscription.plural');
    }
}
