<?php

declare(strict_types=1);

namespace Waterfront\Domain\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                                         $id
 * @property int                                         $subscription_id
 * @property Subscription                                $subscription
 * @property non-negative-int                            $net_price
 * @property CarbonImmutable                             $valid_from
 * @property Collection<int, SubscriptionPriceComponent> $components
 * @property CarbonImmutable                             $created_at
 */
class SubscriptionPrice extends Model
{
    protected $table = 'subscription_prices';

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<SubscriptionPriceComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(SubscriptionPriceComponent::class);
    }
}
