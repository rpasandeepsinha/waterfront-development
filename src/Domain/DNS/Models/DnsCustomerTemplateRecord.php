<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\Customer;

/**
 * @mixin Builder<DnsCustomerTemplateRecord>
 *
 * @property int                 $id
 * @property ?Customer           $customer
 * @property DnsCustomerTemplate $template
 * @property int                 $template_id
 * @property string              $name
 * @property string              $content
 * @property string              $type
 * @property int                 $ttl
 * @property ?int                $port
 * @property ?int                $priority
 * @property ?int                $weight
 * @property bool                $disabled
 * @property ?CarbonImmutable    $created_at
 * @property ?CarbonImmutable    $updated_at
 * @property ?CarbonImmutable    $deleted_at
 */
class DnsCustomerTemplateRecord extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'dns_customer_template_records';

    protected $fillable = [
        'name',
        'content',
        'type',
        'ttl',
        'weight',
        'priority',
        'port',
        'disabled',
    ];

    /**
     * @return BelongsTo<DnsCustomerTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DnsCustomerTemplate::class, 'template_id', 'id');
    }

    /**
     * @return BelongsTo<Customer, DnsCustomerTemplate>
     */
    public function customer(): BelongsTo
    {
        return $this->template->customer();
    }

    protected function casts(): array
    {
        return [
            'disabled' => 'bool',
            'ttl' => 'integer',
            'priority' => 'integer',
        ];
    }
}
