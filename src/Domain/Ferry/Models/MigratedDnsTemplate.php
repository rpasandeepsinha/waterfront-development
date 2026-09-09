<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;

/**
 * @property int              $id
 * @property string           $reference_template_id
 * @property int              $migrated_customer_id
 * @property int              $dns_customer_template_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read MigratedCustomer                           $migratedCustomer
 * @property-read DnsCustomerTemplate|null                   $dnsCustomerTemplate
 * @property-read Collection<int, MigratedDnsTemplateRecord> $migratedDnsTemplateRecords
 *
 * @mixin Builder<MigratedDnsTemplate>
 */
class MigratedDnsTemplate extends Model
{
    public $with = ['dnsCustomerTemplate', 'migratedDnsTemplateRecords'];

    protected $table = 'migrated_dns_templates';

    /** @return BelongsTo<MigratedCustomer, $this> */
    public function migratedCustomer(): BelongsTo
    {
        return $this->belongsTo(MigratedCustomer::class);
    }

    /** @return BelongsTo<DnsCustomerTemplate, $this> */
    public function dnsCustomerTemplate(): BelongsTo
    {
        return $this->belongsTo(DnsCustomerTemplate::class);
    }

    /** @return HasMany<MigratedDnsTemplateRecord, $this> */
    public function migratedDnsTemplateRecords(): HasMany
    {
        return $this->hasMany(MigratedDnsTemplateRecord::class);
    }
}
