<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                                       $id
 * @property int                                       $customer_id
 * @property UuidInterface                             $uuid
 * @property string                                    $key_name
 * @property string                                    $public_key
 * @property string                                    $fingerprint
 * @property ?string                                   $cloudstack_ssh_name
 * @property ?CarbonImmutable                          $created_at
 * @property ?CarbonImmutable                          $updated_at
 * @property Customer                                  $customer
 * @property Collection<int, VirtualMachineDeployment> $virtualMachineDeployments
 * @property Collection<int, ManagerDomainDeployment>  $managerDomains
 *
 * @mixin Builder<SshKey>
 */
class SshKey extends Model implements AuditableContract
{
    use Auditable;
    use HasTimestamps;

    protected $table = 'cloudstack_vm_ssh_keys';

    protected $fillable = [
        'uuid',
        'key_name',
        'public_key',
        'fingerprint',
        'cloudstack_ssh_name',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<VirtualMachineDeployment, $this>
     */
    public function virtualMachineDeployments(): BelongsToMany
    {
        return $this->belongsToMany(
            VirtualMachineDeployment::class,
            'cloudstack_vm_deployment_ssh_key',
            'ssh_key_id',
            'vm_deployment_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ManagerDomainDeployment, $this>
     */
    public function managerDomains(): BelongsToMany
    {
        return $this->belongsToMany(
            ManagerDomainDeployment::class,
            'cloudstack_managerdomain_cloudstack_vm_ssh_keys',
            'ssh_key_id',
            'manager_domain_deployment_id',
        )->withTimestamps();
    }

    protected function casts(): array
    {
        return ['uuid' => UuidCast::class];
    }
}
