<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                      $id
 * @property ?string                  $kpn_customer_id
 * @property ?string                  $kpn_order_id
 * @property ?string                  $tenant_name
 * @property ?string                  $partner_reference
 * @property ?string                  $log
 * @property ?string                  $xml_root_name
 * @property ?int                     $subscription_id
 * @property ?CarbonImmutable         $created_at
 * @property ?CarbonImmutable         $updated_at
 * @property Microsoft365CustomerInfo $microsoft365CustomerInfo
 * @property Microsoft365Deployment   $microsoft365Deployment
 *
 * @mixin Builder<Microsoft365HttpLog>
 */
class Microsoft365HttpLog extends Model
{
    protected $table = 'microsoft365_http_log';

    protected $fillable = [
        'log',
        'xml_root_name',
        'kpn_customer_id',
        'kpn_order_id',
        'partner_reference',
        'tenant_name',
        'subscription_id',
    ];

    /**
     * @return BelongsTo<Microsoft365CustomerInfo, $this>
     */
    public function microsoft365CustomerInfo(): BelongsTo
    {
        return $this->belongsTo(Microsoft365CustomerInfo::class, 'kpn_customer_id', 'kpn_customer_id');
    }

    /**
     * @return BelongsTo<Microsoft365Deployment, $this>
     */
    public function microsoft365Deployment(): BelongsTo
    {
        return $this->belongsTo(Microsoft365Deployment::class, 'kpn_order_id', 'kpn_order_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
