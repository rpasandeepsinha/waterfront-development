<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Support\Enums\LoggingContextKeys;

class BackupDeploymentRepository
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(int $requestId): BackupDeployment
    {
        $backupDeployment = new BackupDeployment();
        $backupDeployment->uuid = Uuid::uuid4();
        $backupDeployment->origin_provisioning_request_id = $requestId;
        $backupDeployment->save();

        return $backupDeployment;
    }

    public function findOrCreate(UuidInterface $tag, int $requestId): BackupDeployment
    {
        $backupDeployment = BackupDeployment::query()
            ->whereHas('request', function (Builder $query) use ($tag) {
                $query->where('tag', $tag);
            })
            ->first();

        if ($backupDeployment !== null) {
            return $backupDeployment;
        }

        $backupDeployment = new BackupDeployment();
        $backupDeployment->uuid = Uuid::uuid4();
        $backupDeployment->origin_provisioning_request_id = $requestId;
        $backupDeployment->save();

        return $backupDeployment;
    }

    /**
     * @return Collection<int, BackupDeployment>
     */
    public function getByTag(UuidInterface $tag): Collection
    {
        return BackupDeployment::query()->whereHas('request', function ($query) use ($tag) {
            $query
                ->where('tag', $tag)
                ->where('request_type', ProvisionType::BACKUP)
                ->whereIn('request_name', [
                    ProvisionRequestName::CREATE_BACKUP,
                    ProvisionRequestName::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION,
                ])
                ->whereHas('result', function ($query) {
                    $query->where('status', ProvisionStatus::SUCCESS);
                });
        })->get();
    }

    public function countCreateRequestsByTag(UuidInterface $tag): int
    {
        return $this->getByTag($tag)->count();
    }

    public function findByTag(UuidInterface $tag): ?BackupDeployment
    {
        return $this->getByTag($tag)->first();
    }

    /**
     * @throws Throwable
     */
    public function deleteBackupAndChildren(BackupDeployment $deployment): bool
    {
        return DB::transaction(function () use ($deployment): bool {
            $deletedChild = $deployment->acronisBackupDeployment()->delete();
            if ($deletedChild === 0) {
                $this->logger->warning('Acronis backup deployment was not found when deleting backup deployment', [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::PROVISIONING_ID => $deployment->id,
                ]);
            }

            return (bool) $deployment->delete();
        });
    }
}
