<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int                                   $id
 * @property string                                $identity_metadata
 * @property string                                $identity_uuid
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 * @property string                                $ip_address
 * @property ?CarbonImmutable                      $completed_at
 * @property ?CarbonImmutable                      $sent_hubspot_at
 * @property Collection<int, CancellationFlowStep> $cancellationFlowSteps
 * @property Collection<int, Subscription>         $subscriptions
 *
 * @mixin Builder<CancellationFlow>
 */
class CancellationFlow extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'cancellation_flows';

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(
            Subscription::class,
            'cancellation_flows_subscriptions',
            'cancellation_flows_id',
            'subscription_id',
        );
    }

    /**
     * @return HasMany<CancellationFlowStep, $this>
     */
    public function cancellationFlowSteps(): HasMany
    {
        return $this->hasMany(CancellationFlowStep::class, 'cancellation_flow_id');
    }

    /**
     * @return HasMany<CancellationFlowSubscriptions, $this>
     */
    public function cancellationFlowSubscriptions(): HasMany
    {
        return $this->hasMany(CancellationFlowSubscriptions::class, 'cancellation_flows_id');
    }
}
