<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Services;

use Carbon\CarbonImmutable;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;

class OneTimeServiceUpdater
{
    public function updateStatus(OneTimeService $oneTimeService, OneTimeServiceStatus $status): void
    {
        $oneTimeService->status = $status;
        $oneTimeService->update();
    }

    public function changeExecutionDate(OneTimeService $oneTimeService, CarbonImmutable $executionDate): void
    {
        $oneTimeService->execution_date = $executionDate;
        $oneTimeService->update();
    }
}
