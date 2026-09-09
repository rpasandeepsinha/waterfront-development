<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                     $usesUniqueIds
 * @property string                  $subscription_uuid
 * @property Subscription            $subscription
 * @property ManagerDomainDeployment $managerDomainDeployment
 * @property string                  $cloudstack_id
 * @property ?CarbonImmutable        $created_at
 * @property ?CarbonImmutable        $updated_at
 * @property ?CarbonImmutable        $deleted_at
 *
 * @mixin Builder<VolumeDeployment>
 */
class VolumeDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'cloudstack_volume_deployments';

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

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup', 'managerDomainDeployment'],
        ];
    }
}
