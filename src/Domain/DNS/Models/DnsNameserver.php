<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int              $id
 * @property string           $nameserver
 * @property DnsRegion        $dnsRegion
 * @property int              $dns_region_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 *
 * @mixin Builder<DnsNameserver>
 */
class DnsNameserver extends Model
{
    use SoftDeletes;
    use HasTimestamps;

    protected $table = 'dns_nameservers';

    protected $fillable = [
        'dns_region_id',
        'nameserver',
    ];

    /**
     * @return BelongsTo<DnsRegion, $this>
     */
    public function dnsRegion(): BelongsTo
    {
        return $this->belongsTo(DnsRegion::class);
    }

    /**
     * @return BelongsToMany<DnsDeployment, $this>
     */
    public function dnsDeployments(): BelongsToMany
    {
        return $this->belongsToMany(
            DnsDeployment::class,
            'dns_deployment_dns_nameserver',
            'dns_nameserver_id',
            'dns_deployment_id',
        );
    }
}
