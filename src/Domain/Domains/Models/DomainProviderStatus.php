<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;

/**
 * @property int              $id
 * @property int              $rtr_response_log_id
 * @property RtrResponseLog   $rtrResponseLog
 * @property int              $provider_id
 * @property Provider         $provider
 * @property int              $domain_deployment_id
 * @property DomainDeployment $domainDeployment
 * @property string           $status
 * @property string           $received_result
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<DomainProviderStatus>
 */
class DomainProviderStatus extends Model
{
    protected $table = 'domain_provider_status';

    protected $fillable = [
        'rtr_response_log_id',
        'provider_id',
        'domain_deployment_id',
        'status',
        'received_result',
    ];

    /**
     * @return HasOne<Provider, $this>
     */
    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    /**
     * @return BelongsTo<DomainDeployment, $this>
     */
    public function domainDeployment(): BelongsTo
    {
        return $this->belongsTo(DomainDeployment::class);
    }

    /**
     * @return HasOne<RtrResponseLog, $this>
     */
    public function rtrResponseLog(): HasOne
    {
        return $this->hasOne(RtrResponseLog::class);
    }
}
