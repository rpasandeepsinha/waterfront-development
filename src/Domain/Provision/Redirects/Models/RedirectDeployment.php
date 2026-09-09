<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                      $id
 * @property UuidInterface            $uuid
 * @property string                   $source
 * @property string                   $destination
 * @property RedirectType             $type
 * @property int                      $origin_provisioning_request_id
 * @property UuidInterface            $context_uuid
 * @property ?CaddyRedirectDeployment $caddyRedirectDeployment
 */
class RedirectDeployment extends ProvisionDeployment
{
    use SoftDeletes;

    protected $table = 'redirect_deployments';

    /**
     * @return HasOne<CaddyRedirectDeployment, $this>
     */
    public function caddyRedirectDeployment(): HasOne
    {
        return $this->hasOne(CaddyRedirectDeployment::class);
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'context_uuid' => UuidCast::class,
            'type' => RedirectType::class,
        ];
    }
}
