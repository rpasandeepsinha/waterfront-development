<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Lighthouse\Casts\IdentityMetadataCast;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

/**
 * @property int                  $id
 * @property ?IdentityMetadataDTO $created_by_metadata
 * @property CustomerType         $customer_type
 * @property int                  $subscription_id
 * @property ?int                 $subscription_mutation_id
 * @property SelectedAction       $selected_action
 * @property string               $puzzel_ticket_id
 * @property ?int                 $credit_amount
 * @property ?int                 $discount_amount
 * @property ?CarbonImmutable     $created_at
 * @property ?CarbonImmutable     $updated_at
 * @property CarbonImmutable      $effective_at
 * @property-read Subscription          $subscription
 * @property-read ?SubscriptionMutation $subscriptionMutation
 *
 * @mixin Builder<CustomerRetentionOffer>
 */
class CustomerRetentionOffer extends Model
{
    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<SubscriptionMutation, $this>
     */
    public function subscriptionMutation(): BelongsTo
    {
        return $this->belongsTo(SubscriptionMutation::class);
    }

    protected function casts(): array
    {
        return [
            'created_by_metadata' => IdentityMetadataCast::class,
            'effective_at' => 'immutable_datetime',
            'customer_type' => CustomerType::class,
            'selected_action' => SelectedAction::class,
        ];
    }
}
