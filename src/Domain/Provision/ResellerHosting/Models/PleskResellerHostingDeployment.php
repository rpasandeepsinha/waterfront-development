<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\ResellerHosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int              $id
 * @property UuidInterface    $uuid
 * @property int              $reseller_hosting_deployment_id
 * @property int              $customer_id
 * @property string           $subscription_domain
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class PleskResellerHostingDeployment extends Model
{
    use SoftDeletes;

    protected $table = 'reseller_hosting_deployments_plesk';

    /**
     * @return BelongsTo<ResellerHostingDeployment, $this>
     */
    public function resellerDeployment(): BelongsTo
    {
        return $this->belongsTo(ResellerHostingDeployment::class, 'reseller_hosting_deployment_id');
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
