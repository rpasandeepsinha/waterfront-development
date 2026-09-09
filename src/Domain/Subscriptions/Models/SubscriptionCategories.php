<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Lighthouse\Casts\IdentityMetadataCast;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;

/**
 * @property int                   $id
 * @property int                   $subscription_id
 * @property ?SubscriptionCategory $name
 * @property Subscription          $subscription
 * @property ?IdentityMetadataDTO  $assignee_metadata
 * @property ?CarbonImmutable      $created_at
 * @property ?CarbonImmutable      $updated_at
 */
class SubscriptionCategories extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'subscription_categories';

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscriptions(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'id', 'subscription_id');
    }

    protected function casts(): array
    {
        return [
            'name' => SubscriptionCategory::class,
            'assignee_metadata' => IdentityMetadataCast::class,
        ];
    }
}
