<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                                   $customer_id
 * @property ?string                               $reference_subscription_id
 * @property ?string                               $reference_product_id
 * @property Collection<int, Customer>             $customer
 * @property Collection<int, MigratedSubscription> $migratedSubscriptions
 * @property Collection<int, Subscription>         $subscriptions
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 *
 * @mixin Builder<MigratedSubscription>
 */
class MigratedSubscription extends Model
{
    protected $table = 'migrated_subscriptions';

    protected $fillable = [
        'reference_product_id',
        'reference_subscription_id',
        'migrated_at',
    ];

    /**
     * @return BelongsToMany<MigratedCustomer, $this>
     */
    public function migratedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(MigratedCustomer::class);
    }

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class);
    }

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function withTechnicalSuccess(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class)->whereIn('technical_status', [
            DomainStatus::ACTIVE->value,
            TechnicalStatus::OK->value,
        ]);
    }

    protected function casts(): array
    {
        return [
            'reference_product_id' => 'string',
            'reference_subscription_id' => 'string',
            'migrated_at' => 'datetime',
        ];
    }
}
