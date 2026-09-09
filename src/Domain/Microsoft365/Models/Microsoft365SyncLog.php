<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                       $id
 * @property string                    $log
 * @property ?int                      $microsoft365_customer_info_id
 * @property ?int                      $microsoft365_deployment_id
 * @property ?CarbonImmutable          $created_at
 * @property ?CarbonImmutable          $updated_at
 * @property ?Microsoft365CustomerInfo $microsoft365CustomerInfo
 * @property ?Microsoft365Deployment   $microsoft365Deployment
 *
 * @mixin Builder<Microsoft365SyncLog>
 */
class Microsoft365SyncLog extends Model
{
    use Prunable;

    protected $table = 'microsoft365_sync_log';

    protected $fillable = [
        'log',
        'microsoft365_customer_info_id',
        'microsoft365_deployment_id',
    ];

    /**
     * @return BelongsTo<Microsoft365CustomerInfo, $this>
     */
    public function microsoft365CustomerInfo(): BelongsTo
    {
        return $this->belongsTo(Microsoft365CustomerInfo::class);
    }

    /**
     * @return BelongsTo<Microsoft365Deployment, $this>
     */
    public function microsoft365Deployment(): BelongsTo
    {
        return $this->belongsTo(Microsoft365Deployment::class);
    }

    /**
     * @return Builder<Microsoft365SyncLog>
     */
    public function prunable(): Builder
    {
        return $this->where('created_at', '<=', CarbonImmutable::now()->subWeeks(6));
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
