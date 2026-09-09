<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Dashboards;

use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Laravel\Nova\Metrics\Metric;
use Waterfront\Apps\Nova\Dashboard\FailedDeployments;
use Waterfront\Apps\Nova\Dashboard\FailedUncategorizedSubscriptions;

class NovaMainDashboard extends Dashboard
{
    public function name(): string
    {
        return 'Dashboard';
    }

    public function uriKey()
    {
        return 'main';
    }

    /**
     * @return array<Metric|Card>
     */
    public function cards(): array
    {
        return [
            new FailedDeployments()->width('1/2'),
            new FailedUncategorizedSubscriptions()->width('1/2'),
        ];
    }
}
