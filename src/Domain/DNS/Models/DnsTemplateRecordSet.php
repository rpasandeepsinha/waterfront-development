<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                                      $id
 * @property string                                   $name
 * @property string                                   $type
 * @property int                                      $ttl
 * @property Collection<int, DnsTemplateRecordSetRow> $rows
 * @property int                                      $template_id
 * @property ?CarbonImmutable                         $created_at
 * @property ?CarbonImmutable                         $updated_at
 *
 * @mixin Builder<DnsTemplateRecordSet>
 */
class DnsTemplateRecordSet extends Model
{
    protected $table = 'dns_template_record_sets';

    protected $fillable = ['name', 'type', 'ttl'];

    /**
     * @return HasMany<DnsTemplateRecordSetRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(DnsTemplateRecordSetRow::class, 'template_record_set_id');
    }
}
