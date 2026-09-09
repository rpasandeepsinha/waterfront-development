<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;

/**
 * @property int               $id
 * @property RtrResponseSource $source
 * @property string            $response
 * @property ?int              $rtr_notification_id
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 * @property ?CarbonImmutable  $processed_at
 * @property ?CarbonImmutable  $failed_at
 *
 * @mixin Builder<RtrResponseLog>
 */
class RtrResponseLog extends Model
{
    protected $table = 'rtr_response_log';

    protected $fillable = [
        'source',
        'response',
        'rtr_notification_id',
        'processed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => RtrResponseSource::class,
        ];
    }
}
