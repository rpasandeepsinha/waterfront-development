<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<DnsTemplateRecordSetRow>
 *
 * @property string               $content
 * @property int                  $template_record_set_id
 * @property DnsTemplateRecordSet $dnsTemplateRecordSet
 * @property ?CarbonImmutable     $created_at
 * @property ?CarbonImmutable     $updated_at
 */
class DnsTemplateRecordSetRow extends Model
{
    protected $table = 'dns_template_record_set_rows';

    protected $fillable = ['content'];

    /**
     * @return BelongsTo<DnsTemplateRecordSet, $this>
     */
    public function dnsTemplateRecordSet(): BelongsTo
    {
        return $this->belongsTo(DnsTemplateRecordSet::class, 'template_record_set_id');
    }
}
