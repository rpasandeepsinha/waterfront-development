<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;

/**
 * @property ?BasekitSitebuilderDeployment $basekitDeployment
 * @property ?BasekitContext               $basekitContext
 * @property ?ProvisioningRequest          $originRequest
 * @property string                        $domain
 * @property UuidInterface                 $uuid
 * @property int                           $origin_provisioning_request_id
 */
class SitebuilderDeployment extends ProvisionDeployment
{
    /**
     * @return HasOne<BasekitSitebuilderDeployment, $this>
     */
    public function basekitDeployment(): HasOne
    {
        return $this->hasOne(BasekitSitebuilderDeployment::class);
    }

    /**
     * @return BelongsTo<ProvisioningRequest, $this>
     */
    public function originRequest(): BelongsTo
    {
        return $this->belongsTo(ProvisioningRequest::class, 'origin_provisioning_request_id');
    }

    /**
     * @return HasOneThrough<BasekitContext, ProvisioningRequest, $this>
     */
    public function basekitContext(): HasOneThrough
    {
        return $this->hasOneThrough(
            related: BasekitContext::class,
            through: ProvisioningRequest::class,
            firstKey: 'id',
            secondKey: 'context_uuid',
            localKey: 'origin_provisioning_request_id',
            secondLocalKey: 'context_uuid'
        );
    }
}
