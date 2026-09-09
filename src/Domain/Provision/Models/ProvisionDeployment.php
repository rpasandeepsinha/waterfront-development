<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                 $id
 * @property UuidInterface       $uuid
 * @property int                 $origin_provisioning_request_id
 * @property ?CarbonImmutable    $created_at
 * @property ?CarbonImmutable    $updated_at
 * @property ?CarbonImmutable    $deleted_at
 * @property ProvisioningRequest $request
 *
 * @mixin Builder<ProvisionDeployment>
 */
abstract class ProvisionDeployment extends Model
{
    use SoftDeletes;

    /**
     * Override to prevent Laravel 13's HasCollection trait from trying to instantiate
     * this abstract class when traversing the parent chain for a CollectedBy attribute.
     */
    public function resolveCollectionFromAttribute(): ?string
    {
        return null;
    }

    /**
     * @return BelongsTo<ProvisioningRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ProvisioningRequest::class, 'origin_provisioning_request_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
        ];
    }
}
