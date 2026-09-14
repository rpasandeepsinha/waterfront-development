<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Repositories;

use Carbon\CarbonImmutable;
use Waterfront\Domain\VPS\Enums\JobStatus;
use Waterfront\Domain\VPS\Models\CloudstackJob;

class CloudstackJobRepository
{
    private const int ACTIVE_JOB_TTL_MINUTES = 60;

    /**
     * @param array<string, mixed> $jobArray
     */
    public function create(array $jobArray): CloudstackJob
    {
        return CloudstackJob::create($jobArray);
    }

    public function hasActiveJobForVmDeployment(int $vmDeploymentId): bool
    {
        return CloudstackJob::query()
            ->where('vm_deployment_id', $vmDeploymentId)
            ->whereNull('cloudstack_completed')
            ->where(function ($q): void {
                $q->whereNull('status')->orWhere('status', JobStatus::PENDING->value);
            })
            ->where('updated_at', '>=', CarbonImmutable::now()->subMinutes(self::ACTIVE_JOB_TTL_MINUTES))
            ->exists();
    }
}
