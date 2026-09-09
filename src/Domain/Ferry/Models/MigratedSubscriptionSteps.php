<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                         $id
 * @property int                         $subscription_id
 * @property MigrationStep               $step
 * @property MigrationSubscriptionStatus $status
 * @property ?CarbonImmutable            $executed_at
 * @property ?CarbonImmutable            $created_at
 * @property ?CarbonImmutable            $updated_at
 *
 * @mixin Builder<MigratedSubscriptionSteps>
 */
class MigratedSubscriptionSteps extends Model
{
    protected $table = 'migrated_subscription_steps';

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    protected function casts(): array
    {
        return [
            'step' => MigrationStep::class,
            'status' => MigrationSubscriptionStatus::class,
            'executed_at' => 'datetime',
        ];
    }
}
