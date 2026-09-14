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
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @property int                                       $id
 * @property int                                       $customer_id
 * @property int                                       $environment_id
 * @property ?string                                   $domain_id
 * @property string                                    $domain_name
 * @property string                                    $account
 * @property string                                    $username
 * @property ?CarbonImmutable                          $last_subscription_sync_at
 * @property Environment                               $environment
 * @property Collection<int, VolumeDeployment>         $volumeDeployments
 * @property Collection<int, VirtualMachineDeployment> $virtualMachineDeployments
 * @property Collection<int, SshKey>                   $sshKeys
 * @property ?CarbonImmutable                          $created_at
 * @property ?CarbonImmutable                          $updated_at
 * @property ?CarbonImmutable                          $deleted_at
 * @property Customer                                  $customer
 *
 * @mixin Builder<ManagerDomainDeployment>
 */
class ManagerDomainDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'cloudstack_managerdomain_deployments';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Environment, $this>
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return HasMany<VolumeDeployment, $this>
     */
    public function volumeDeployments(): HasMany
    {
        return $this->hasMany(VolumeDeployment::class, 'manager_domain_deployment_id', 'id');
    }

    /**
     * @return HasMany<VirtualMachineDeployment, $this>
     */
    public function virtualMachineDeployments(): HasMany
    {
        return $this->hasMany(VirtualMachineDeployment::class, 'manager_domain_deployment_id', 'id');
    }

    /**
     * @return BelongsToMany<SshKey, $this>
     */
    public function sshKeys(): BelongsToMany
    {
        return $this->belongsToMany(
            SshKey::class,
            'cloudstack_managerdomain_cloudstack_vm_ssh_keys',
            'manager_domain_deployment_id',
            'ssh_key_id',
        )->withTimestamps();
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup'],
        ];
    }

    protected function casts(): array
    {
        return [
            'last_subscription_sync_at' => 'datetime',
        ];
    }
}
