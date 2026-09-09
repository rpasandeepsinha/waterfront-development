<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Sitebuilder\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                   $id
 * @property int                   $site_ref
 * @property int                   $sitebuilder_deployment_id
 * @property UuidInterface         $uuid
 * @property SitebuilderDeployment $sitebuilderDeployment
 * @property BasekitContext        $basekitContext
 * @property ?CarbonImmutable      $created_at
 * @property ?CarbonImmutable      $updated_at
 */
class BasekitSitebuilderDeployment extends Model
{
    use SoftDeletes;

    protected $table = 'sitebuilder_deployments_basekit';

    /**
     * @return BelongsTo<SitebuilderDeployment, $this>
     */
    public function sitebuilderDeployment(): BelongsTo
    {
        return $this->belongsTo(SitebuilderDeployment::class, 'sitebuilder_deployment_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
        ];
    }
}
