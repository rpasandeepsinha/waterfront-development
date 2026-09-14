<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                                 $id
 * @property UuidInterface                       $context_uuid
 * @property string                              $host
 * @property Collection<int, RedirectDeployment> $redirectDeployments
 * @property ?CarbonImmutable                    $created_at
 * @property ?CarbonImmutable                    $updated_at
 * @property ?CarbonImmutable                    $deleted_at
 */
class CaddyContext extends Model
{
    use SoftDeletes;

    protected $table = 'redirects_context_caddy';

    /**
     * @return HasManyThrough<RedirectDeployment, ProvisioningRequest, $this>
     */
    public function redirectDeployments(): HasManyThrough
    {
        return $this->hasManyThrough(
            related: RedirectDeployment::class,
            through: ProvisioningRequest::class,
            firstKey: 'context_uuid',
            secondKey: 'origin_provisioning_request_id',
            localKey: 'context_uuid',
            secondLocalKey: 'id',
        );
    }

    protected function casts(): array
    {
        return [
            'context_uuid' => UuidCast::class,
        ];
    }
}
