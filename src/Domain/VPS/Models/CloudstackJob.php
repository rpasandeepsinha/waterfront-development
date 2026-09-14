<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                       $id
 * @property string                    $subscription_uuid
 * @property string                    $job_id
 * @property ?string                   $user_id
 * @property ?string                   $account_id
 * @property ?string                   $cmd
 * @property ?int                      $status
 * @property ?int                      $proc_status
 * @property ?int                      $result_code
 * @property ?string                   $result_type
 * @property ?string                   $instance_type
 * @property ?string                   $instance_id
 * @property ?CarbonImmutable          $cloudstack_created
 * @property ?CarbonImmutable          $cloudstack_completed
 * @property ?VirtualMachineDeployment $virtualMachineDeployment
 * @property string|null               $template_uuid
 * @property string|null               $ssh_key_uuid
 * @property ?CarbonImmutable          $created_at
 * @property ?CarbonImmutable          $updated_at
 *
 * @mixin Builder<CloudstackJob>
 */
class CloudstackJob extends Model
{
    use Prunable;

    protected $table = 'cloudstack_jobs';

    protected $fillable = [
        'vm_deployment_id',
        'job_id',
        'user_id',
        'account_id',
        'cmd',
        'status',
        'proc_status',
        'result_code',
        'result_type',
        'instance_type',
        'instance_id',
        'cloudstack_created',
        'cloudstack_completed',
        'template_uuid',
        'ssh_key_uuid',
    ];

    /**
     * @return BelongsTo<VirtualMachineDeployment, $this>
     */
    public function virtualMachineDeployment(): BelongsTo
    {
        return $this->belongsTo(VirtualMachineDeployment::class, 'vm_deployment_id', 'id');
    }

    /**
     * @return Builder<CloudstackJob>
     */
    public function prunable(): Builder
    {
        return $this->where('created_at', '<=', CarbonImmutable::now()->subMonth());
    }

    protected function casts(): array
    {
        return [
            'cloudstack_created' => 'datetime:datetime:Y-m-d\TH:i:sO',
            'cloudstack_completed' => 'datetime:datetime:Y-m-d\TH:i:sO',
        ];
    }
}
