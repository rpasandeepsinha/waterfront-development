<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;

/**
 * @mixin Builder<DnsCustomerTemplate>
 *
 * @property int                                        $id
 * @property Customer                                   $customer
 * @property Collection<int, DnsCustomerTemplateRecord> $records
 * @property Collection<int, DomainDeployment>          $domainDeployments
 * @property int                                        $customer_id
 * @property string                                     $name
 * @property-read MigratedDnsTemplate|null              $migratedDnsTemplate
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 */
class DnsCustomerTemplate extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'dns_customer_templates';

    protected $fillable = [
        'name',
        'customer_id',
    ];

    /**
     * @return HasMany<DnsCustomerTemplateRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(DnsCustomerTemplateRecord::class, 'template_id', 'id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<DomainDeployment, $this>
     */
    public function domainDeployments(): HasMany
    {
        return $this->hasMany(DomainDeployment::class, 'template_id', 'id');
    }

    /**
     * @return HasOne<MigratedDnsTemplate, $this>
     */
    public function migratedDnsTemplate(): HasOne
    {
        return $this->hasOne(MigratedDnsTemplate::class);
    }
}
