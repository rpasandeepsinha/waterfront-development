<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                                    $id
 * @property UuidInterface                          $context_uuid
 * @property int                                    $user_ref
 * @property Collection<int, SitebuilderDeployment> $sitebuilderDeployments
 * @property ?CarbonImmutable                       $created_at
 * @property ?CarbonImmutable                       $updated_at
 */
class BasekitContext extends Model
{
    use SoftDeletes;

    protected $table = 'sitebuilder_context_basekit';

    /**
     * @return HasManyThrough<SitebuilderDeployment, ProvisioningRequest, $this>
     */
    public function sitebuilderDeployments(): HasManyThrough
    {
        return $this->hasManyThrough(
            related: SitebuilderDeployment::class,
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
