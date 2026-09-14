<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                  $id
 * @property UuidInterface        $uuid
 * @property int                  $capacity
 * @property CarbonImmutable      $start_timeslot
 * @property CarbonImmutable      $end_timeslot
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @mixin Builder<PuzzelCallbackTimeslot>
 */
class PuzzelCallbackTimeslot extends Model
{
    protected $table = 'puzzel_callback_timeslots';

    /**
     * @return HasMany<PuzzelCallbackRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(
            PuzzelCallbackRequest::class,
            'puzzel_callback_timeslot_id',
        );
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'start_timeslot' => 'immutable_datetime:H:i:s',
            'end_timeslot' => 'immutable_datetime:H:i:s',
        ];
    }
}
