<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Subscriptions\Enums\CancellationStepType;

/**
 * @property int                  $id
 * @property int                  $cancellation_flow_id
 * @property CancellationStepType $type
 * @property string               $step_data
 * @property string               $response_data
 * @property CancellationFlow     $cancellationFlow
 * @property ?CarbonImmutable     $created_at
 * @property ?CarbonImmutable     $updated_at
 * @property ?CarbonImmutable     $completed_at
 *
 * @mixin Builder<CancellationFlowStep>
 */
class CancellationFlowStep extends Model
{
    protected $table = 'cancellation_flow_steps';

    /**
     * @return BelongsTo<CancellationFlow, $this>
     */
    public function CancellationFlow(): BelongsTo
    {
        return $this->belongsTo(CancellationFlow::class);
    }

    protected function casts(): array
    {
        return [
            'type' => CancellationStepType::class,
        ];
    }
}
