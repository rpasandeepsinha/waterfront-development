<?php

declare(strict_types=1);

namespace Waterfront\Domain\Marketing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Marketing\Enums\HubspotObjectType;

/**
 * @property-read int $id
 * @property HubspotObjectType $sandwave_object_type
 * @property UuidInterface     $sandwave_object_id
 * @property string            $hubspot_object_id
 * @property CarbonImmutable   $synced_at
 *
 * @mixin Builder<HubspotObjectSync>
 */
class HubspotObjectSync extends Model
{
    protected $table = 'hubspot_object_sync';
}
