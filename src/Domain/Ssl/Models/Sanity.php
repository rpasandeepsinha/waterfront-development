<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @mixin Builder<Sanity>
 *
 * @property string           $subscription_uuid
 * @property ?string          $product_name
 * @property ?string          $domain
 * @property ?bool            $has_ssl_subscription
 * @property ?bool            $has_ssl_certificate_files
 * @property ?bool            $has_ssl_private_files
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Sanity extends Model
{
    protected $table = 'ssl_sanity';

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'has_ssl_subscription' => 'boolean',
            'has_ssl_certificate_files' => 'boolean',
            'has_ssl_private_files' => 'boolean',
        ];
    }
}
