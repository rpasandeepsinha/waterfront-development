<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int              $id
 * @property string           $nameserver
 * @property DnsDeployment    $dnsDeployments
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<DnsVanityNameserver>
 */
class DnsVanityNameserver extends Model
{
    protected $table = 'dns_vanity_nameservers';

    protected $fillable = [
        'nameserver',
    ];

    /**
     * @return BelongsToMany<DnsDeployment, $this>
     */
    public function dnsDeployments(): BelongsToMany
    {
        return $this->belongsToMany(DnsDeployment::class)->withTimestamps();
    }
}
