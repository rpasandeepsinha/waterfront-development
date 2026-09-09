<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\ResellerHosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int              $id
 * @property UuidInterface    $uuid
 * @property int              $origin_provisioning_request_id
 * @property int              $server_id
 * @property ?string          $domain
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class ResellerHostingDeployment extends ProvisionDeployment
{
    protected $table = 'provisioning_reseller_hosting_deployments';

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    /**
     * @return HasOne<DirectAdminResellerHostingDeployment, $this>
     */
    public function directAdminDeployment(): HasOne
    {
        return $this->hasOne(DirectAdminResellerHostingDeployment::class);
    }

    /**
     * @return HasOne<PleskResellerHostingDeployment, $this>
     */
    public function pleskDeployment(): HasOne
    {
        return $this->hasOne(PleskResellerHostingDeployment::class);
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
