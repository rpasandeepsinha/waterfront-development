<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\VPS\Enums\VpsActionStatus;

/**
 * @property int                            $id
 * @property string                         $subscription_uuid
 * @property int                            $manager_domain_deployment_id
 * @property Subscription                   $subscription
 * @property ManagerDomainDeployment        $managerDomainDeployment
 * @property ?string                        $cloudstack_id
 * @property ?CarbonImmutable               $last_result_received
 * @property ?string                        $last_result
 * @property ?VpsActionStatus               $last_action_status
 * @property ?string                        $custom_name
 * @property Collection<int, CloudstackJob> $cloudstackJobs
 * @property Collection<int, SshKey>        $sshKeys
 * @property ?CarbonImmutable               $created_at
 * @property ?CarbonImmutable               $updated_at
 * @property ?CarbonImmutable               $deleted_at
 *
 * @mixin Builder<VirtualMachineDeployment>
 */
class VirtualMachineDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'cloudstack_vm_deployments';

    protected $fillable = [
        'subscription_uuid',
        'manager_domain_deployment_id',
        'last_result',
        'last_result_received',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<ManagerDomainDeployment, $this>
     */
    public function managerDomainDeployment(): BelongsTo
    {
        return $this->belongsTo(ManagerDomainDeployment::class, 'manager_domain_deployment_id', 'id');
    }

    /**
     * @return HasMany<CloudstackJob, $this>
     */
    public function cloudstackJobs(): HasMany
    {
        return $this->hasMany(CloudstackJob::class, 'vm_deployment_id');
    }

    /**
     * @return BelongsToMany<SshKey, $this>
     */
    public function sshKeys(): BelongsToMany
    {
        return $this->belongsToMany(
            SshKey::class,
            'cloudstack_vm_deployment_ssh_key',
            'vm_deployment_id',
            'ssh_key_id',
        )->withTimestamps();
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup', 'managerDomainDeployment'],
        ];
    }

    protected function casts(): array
    {
        return [
            'last_result_received' => 'datetime',
            'last_action_status' => VpsActionStatus::class,
        ];
    }
}
