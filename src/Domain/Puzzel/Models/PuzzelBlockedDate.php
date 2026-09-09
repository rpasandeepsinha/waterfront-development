<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int             $id
 * @property CarbonImmutable $date
 * @property ?string         $reason
 *
 * @mixin Builder<PuzzelBlockedDate>
 */
class PuzzelBlockedDate extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'immutable_datetime',
        ];
    }
}
