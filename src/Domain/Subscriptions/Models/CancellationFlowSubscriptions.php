<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int              $cancellation_flows_id
 * @property int              $subscription_id
 * @property ?CarbonImmutable $uncancelled_at
 *
 * @mixin Builder<CancellationFlow>
 */
#[WithoutTimestamps]
class CancellationFlowSubscriptions extends Model
{
    protected $table = 'cancellation_flows_subscriptions';

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<CancellationFlow, $this>
     */
    public function cancellationFlow(): HasMany
    {
        return $this->hasMany(CancellationFlow::class);
    }
}
