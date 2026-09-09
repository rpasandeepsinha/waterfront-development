<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Servers\Models\Server;

/**
 * @property int     $server_id
 * @property ?string $domain
 * @property Server  $server
 */
class HostingDeployment extends ProvisionDeployment
{
    protected $table = 'provisioning_hosting_deployments';

    /**
     * @see https://yh-jira.atlassian.net/browse/SWD-9161
     *
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'server_id');
    }

    /**
     * @return HasOne<PleskHostingDeployment, $this>
     */
    public function pleskDeployment(): HasOne
    {
        return $this->hasOne(PleskHostingDeployment::class);
    }

    /**
     * @return HasOne<DirectAdminHostingDeployment, $this>
     */
    public function directAdminDeployment(): HasOne
    {
        return $this->hasOne(DirectAdminHostingDeployment::class);
    }
}
