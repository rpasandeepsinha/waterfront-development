<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Hosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int               $id
 * @property UuidInterface     $uuid
 * @property string            $customer_name
 * @property string            $subscription_domain
 * @property int               $hosting_deployment_id
 * @property ?CarbonImmutable  $created_at
 * @property ?CarbonImmutable  $updated_at
 * @property HostingDeployment $hostingDeployment
 */
class PleskHostingDeployment extends Model
{
    use SoftDeletes;

    protected $table = 'hosting_deployments_plesk';

    /**
     * @return BelongsTo<HostingDeployment, $this>
     */
    public function hostingDeployment(): BelongsTo
    {
        return $this->belongsTo(HostingDeployment::class, 'hosting_deployment_id');
    }

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
        ];
    }
}
