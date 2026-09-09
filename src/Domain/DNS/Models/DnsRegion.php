<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property Collection<int, DnsNameserver> $dnsNameservers
 * @property int                            $id
 * @property string                         $name
 * @property ?CarbonImmutable               $created_at
 * @property ?CarbonImmutable               $updated_at
 * @property ?CarbonImmutable               $deleted_at
 *
 * @mixin Builder<DnsRegion>
 */
class DnsRegion extends Model
{
    use SoftDeletes;
    use HasTimestamps;

    protected $table = 'dns_regions';

    protected $fillable = [
        'name',
    ];

    /**
     * @return HasMany<DnsNameserver, $this>
     */
    public function dnsNameservers(): HasMany
    {
        return $this->hasMany(DnsNameserver::class);
    }
}
