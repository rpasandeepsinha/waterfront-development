<?php

declare(strict_types=1);

namespace Waterfront\Domain\Experiment\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                           $id
 * @property ExperimentType                $slug
 * @property Collection<int, Subscription> $subscriptions
 * @property Collection<int, Product>      $products
 * @property ?CarbonImmutable              $created_at
 * @property ?CarbonImmutable              $updated_at
 * @property ?CarbonImmutable              $deleted_at
 */
class Experiment extends Model
{
    protected $table = 'experiment';

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'experiment_products', 'experiment_id', 'product_id');
    }

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(Subscription::class, 'experiment_subscriptions', 'experiment_id', 'subscription_id');
    }

    protected function casts(): array
    {
        return [
            'slug' => ExperimentType::class,
        ];
    }
}
