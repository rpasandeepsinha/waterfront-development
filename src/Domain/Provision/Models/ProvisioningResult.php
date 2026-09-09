<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                 $id
 * @property UuidInterface       $uuid
 * @property int                 $request_id
 * @property ProvisioningRequest $provisioningRequest
 * @property string              $response
 * @property ProvisionStatus     $status
 * @property ?CarbonImmutable    $created_at
 * @property ?CarbonImmutable    $updated_at
 *
 * @mixin Builder<ProvisioningResult>
 */
class ProvisioningResult extends Model
{
    public bool $failed {
        get => in_array($this->status, ProvisionStatus::getFailedStatuses(), true);
    }

    public bool $succeeded {
        get => ! $this->failed;
    }

    protected $table = 'provisioning_results';

    /** @return BelongsTo<ProvisioningRequest, $this> */
    public function provisioningRequest(): BelongsTo
    {
        return $this->belongsTo(ProvisioningRequest::class, 'request_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'status' => ProvisionStatus::class,
            'request_created_at' => 'datetime',
        ];
    }
}
