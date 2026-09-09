<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int              $id
 * @property int              $dns_deployment_id
 * @property DnsDeployment    $dnsDeployment
 * @property string           $nameserver
 * @property ?string          $ipv4
 * @property ?string          $ipv6
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<DnsExternalNameserver>
 */
class DnsExternalNameserver extends Model implements AuditableContract
{
    use HasTimestamps;
    use Auditable;

    protected $fillable = [
        'nameserver',
        'ipv4',
        'ipv6',
    ];

    /**
     * @return BelongsTo<DnsDeployment, $this>
     */
    public function dnsDeployment(): BelongsTo
    {
        return $this->belongsTo(DnsDeployment::class);
    }
}
