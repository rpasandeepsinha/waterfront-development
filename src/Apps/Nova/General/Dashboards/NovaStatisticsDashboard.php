<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Dashboards;

use Illuminate\Support\Facades\App;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Metrics\Metric;
use Waterfront\Apps\Nova\Customers\Metrics\NovaTotalCustomersMetric;
use Waterfront\Apps\Nova\Subscriptions\Metrics\NovaTotalSubscriptionsMetric;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaStatisticsDashboard extends Dashboard
{
    public function name(): string
    {
        return 'Statistieken';
    }

    public function uriKey()
    {
        return 'statistics';
    }

    /**
     * @return array<Metric>
     */
    public function cards(): array
    {
        $translator = App::make(TranslatorInterface::class);

        return [
            new NovaTotalCustomersMetric($translator)->width('1/2'),
            new NovaTotalSubscriptionsMetric($translator)->width('1/2'),
        ];
    }
}
