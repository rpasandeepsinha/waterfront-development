<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string                                $slug
 * @property Collection<int, DnsTemplateRecordSet> $recordSets
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 *
 * @mixin Builder<DnsTemplate>
 */
class DnsTemplate extends Model
{
    protected $table = 'dns_templates';

    protected $fillable = ['slug'];

    /**
     * @return HasMany<DnsTemplateRecordSet, $this>
     */
    public function recordSets(): HasMany
    {
        return $this->hasMany(DnsTemplateRecordSet::class, 'template_id');
    }
}
