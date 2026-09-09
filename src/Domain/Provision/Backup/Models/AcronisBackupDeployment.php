<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int              $id
 * @property UuidInterface    $uuid
 * @property int              $backup_deployment_id
 * @property BackupDeployment $backupDeployment
 * @property int              $acronis_provider_id
 * @property AcronisProvider  $acronisProvider
 * @property UuidInterface    $tenant_uuid
 * @property UuidInterface    $user_uuid
 * @property ?CarbonImmutable $deleted_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class AcronisBackupDeployment extends Model
{
    use SoftDeletes;

    protected $table = 'backup_deployments_acronis';

    /**
     * @return BelongsTo<BackupDeployment, $this>
     */
    public function backupDeployment(): BelongsTo
    {
        return $this->belongsTo(BackupDeployment::class, 'backup_deployment_id');
    }

    /**
     * @return BelongsTo<AcronisProvider, $this>
     */
    public function acronisProvider(): BelongsTo
    {
        return $this->belongsTo(AcronisProvider::class, 'acronis_provider_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'tenant_uuid' => UuidCast::class,
            'user_uuid' => UuidCast::class,
        ];
    }
}
