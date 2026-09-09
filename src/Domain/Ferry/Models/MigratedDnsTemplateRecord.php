<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;

/**
 * @property int              $id
 * @property string           $reference_record_id
 * @property int              $dns_customer_template_record_id
 * @property int              $migrated_dns_template_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read MigratedDnsTemplate $migratedDnsTemplate
 * @property-read DnsCustomerTemplateRecord $dnsCustomerTemplateRecord
 *
 * @mixin Builder<MigratedDnsTemplateRecord>
 */
class MigratedDnsTemplateRecord extends Model
{
    public $with = ['dnsCustomerTemplateRecord'];

    protected $table = 'migrated_dns_template_records';

    /** @return BelongsTo<MigratedDnsTemplate, $this> */
    public function migratedDnsTemplate(): BelongsTo
    {
        return $this->belongsTo(MigratedDnsTemplate::class);
    }

    /** @return BelongsTo<DnsCustomerTemplateRecord, $this> */
    public function dnsCustomerTemplateRecord(): BelongsTo
    {
        return $this->belongsTo(DnsCustomerTemplateRecord::class);
    }
}
