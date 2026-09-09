<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                                  $id
 * @property int                                  $subscription_id
 * @property Subscription                         $subscription
 * @property Collection<int, Subscription>        $subscriptionChildren
 * @property string                               $technical_status
 * @property Microsoft365OrderStatus              $kpn_status
 * @property ?int                                 $kpn_order_id
 * @property int                                  $microsoft365_customer_info_id
 * @property ?CarbonImmutable                     $kpn_start_date
 * @property ?CarbonImmutable                     $created_at
 * @property ?CarbonImmutable                     $updated_at
 * @property Microsoft365CustomerInfo             $microsoft365CustomerInfo
 * @property Customer                             $customer
 * @property Collection<int, Microsoft365HttpLog> $microsoft365HttpLogs
 * @property Collection<int, Microsoft365SyncLog> $microsoft365SyncLogs
 *
 * @mixin Builder<Microsoft365Deployment>
 */
class Microsoft365Deployment extends Model
{
    protected $table = 'microsoft365_deployments';

    protected $fillable = [
        'subscription_id',
        'technical_status',
        'kpn_order_id',
        'kpn_status',
        'microsoft365_customer_info_id',
    ];

    public function getKpnStatusAttribute(): Microsoft365OrderStatus
    {
        $status = $this->attributes['kpn_status'] ?? Microsoft365OrderStatus::ACTIVE;

        return $status instanceof Microsoft365OrderStatus ? $status : Microsoft365OrderStatus::from($status);
    }

    public function setKpnStatusAttribute(Microsoft365OrderStatus|string $status): void
    {
        $this->attributes['kpn_status'] = $status instanceof Microsoft365OrderStatus ? $status : Microsoft365OrderStatus::from($status);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptionChildren(): HasMany
    {
        return $this->hasMany(Subscription::class, 'parent_subscription_id', 'subscription_id');
    }

    /**
     * @return BelongsTo<Microsoft365CustomerInfo, $this>
     */
    public function microsoft365CustomerInfo(): BelongsTo
    {
        return $this->belongsTo(Microsoft365CustomerInfo::class);
    }

    /**
     * @return HasOneThrough<Customer, Microsoft365CustomerInfo, $this>
     */
    public function customer(): HasOneThrough
    {
        return $this->hasOneThrough(
            Customer::class,
            Microsoft365CustomerInfo::class,
            'customer_id',
            'id',
            'microsoft365_customer_info_id',
            'id'
        );
    }

    /**
     * @return HasMany<Microsoft365HttpLog, $this>
     */
    public function microsoft365HttpLogs(): HasMany
    {
        return $this->hasMany(Microsoft365HttpLog::class, 'kpn_order_id', 'kpn_order_id');
    }

    /**
     * @return HasMany<Microsoft365SyncLog, $this>
     */
    public function microsoft365SyncLogs(): HasMany
    {
        return $this->hasMany(Microsoft365SyncLog::class, 'microsoft365_deployment_id', 'id');
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup', 'microsoft365CustomerInfo'],
        ];
    }

    protected function casts(): array
    {
        return [
            'kpn_order_id' => 'int',
            'kpn_start_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
