<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;

/**
 * @property int                                     $id
 * @property int                                     $customer_id
 * @property Customer                                $customer
 * @property CustomerInfoType                        $type
 * @property ?Microsoft365ProcessStatus              $technical_status
 * @property ?string                                 $tenant_name
 * @property ?int                                    $tenant_order_id
 * @property ?string                                 $kpn_customer_id
 * @property ?string                                 $tenant_id
 * @property bool                                    $tenant_access_verified
 * @property ?string                                 $primary_domain
 * @property ?PrimaryDomainStatus                    $primary_domain_status
 * @property CarbonImmutable                         $synced_at
 * @property ?CarbonImmutable                        $mca_signed_at
 * @property ?CarbonImmutable                        $created_at
 * @property ?CarbonImmutable                        $updated_at
 * @property Collection<int, Microsoft365Deployment> $microsoft365Deployments
 * @property Collection<int, Microsoft365HttpLog>    $microsoft365HttpLogs
 * @property Collection<int, Microsoft365SyncLog>    $microsoft365SyncLogs
 *
 * @mixin Builder<Microsoft365CustomerInfo>
 */
class Microsoft365CustomerInfo extends Model
{
    protected $table = 'microsoft365_customer_info';

    protected $fillable = [
        'customer_id',
        'type',
        'technical_status',
        'tenant_name',
        'tenant_order_id',
        'kpn_customer_id',
        'tenant_id',
        'tenant_access_verified',
        'primary_domain',
        'primary_domain_status',
        'synced_at',
        'mca_signed_at',
    ];

    public function getTechnicalStatusAttribute(): Microsoft365ProcessStatus
    {
        $status = $this->attributes['technical_status'] ?? Microsoft365ProcessStatus::ACTIVE;

        return $status instanceof Microsoft365ProcessStatus ? $status : Microsoft365ProcessStatus::from($status);
    }

    public function setTechnicalStatusAttribute(Microsoft365ProcessStatus|string $status): void
    {
        $this->attributes['technical_status'] = $status instanceof Microsoft365ProcessStatus
            ? $status
            : Microsoft365ProcessStatus::from($status);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<Microsoft365Deployment, $this>
     */
    public function microsoft365Deployments(): HasMany
    {
        return $this->hasMany(Microsoft365Deployment::class);
    }

    /**
     * @return HasMany<Microsoft365HttpLog, $this>
     */
    public function microsoft365HttpLogs(): HasMany
    {
        return $this->hasMany(Microsoft365HttpLog::class, 'kpn_customer_id', 'kpn_customer_id');
    }

    /**
     * @return HasMany<Microsoft365SyncLog, $this>
     */
    public function microsoft365SyncLogs(): HasMany
    {
        return $this->hasMany(Microsoft365SyncLog::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CustomerInfoType::class,
            'synced_at' => 'datetime',
            'mca_signed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'tenant_access_verified' => 'boolean',
            'primary_domain_status' => PrimaryDomainStatus::class,
        ];
    }
}
