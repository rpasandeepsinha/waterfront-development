<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                $id
 * @property UuidInterface      $uuid
 * @property int                $redirect_deployment_id
 * @property string             $caddy_id
 * @property RedirectDeployment $redirectDeployment
 * @property ?CarbonImmutable   $created_at
 * @property ?CarbonImmutable   $updated_at
 * @property ?CarbonImmutable   $deleted_at
 */
class CaddyRedirectDeployment extends ProvisionDeployment
{
    use SoftDeletes;

    protected $table = 'redirect_deployments_caddy';

    /**
     * @return BelongsTo<RedirectDeployment, $this>
     */
    public function redirectDeployment(): BelongsTo
    {
        return $this->belongsTo(RedirectDeployment::class, 'redirect_deployment_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
        ];
    }
}
