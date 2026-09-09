<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                      $id
 * @property int                      $customer_id
 * @property ?int                     $subscription_id
 * @property int                      $product_id
 * @property CarbonImmutable          $start_date
 * @property CarbonImmutable          $end_date
 * @property int                      $period
 * @property ?int                     $gross_price
 * @property int                      $net_price
 * @property string                   $vat_code
 * @property float                    $vat_rate
 * @property int                      $ledger_code
 * @property ?CarbonImmutable         $sent_to_harbor_at
 * @property ?CarbonImmutable         $announced_by_harbor_at
 * @property bool                     $paid
 * @property ?int                     $parent_invoice_id
 * @property ?int                     $merge_on_pdf_with_invoice_id
 * @property ?Invoice                 $parentInvoice
 * @property ?Invoice                 $mergeOnPdfWithInvoice
 * @property ?InvoiceLineCreditReason $credit_reason
 * @property ?CarbonImmutable         $created_at
 * @property ?CarbonImmutable         $updated_at
 * @property ?Subscription            $subscription
 * @property Product                  $product
 * @property Customer                 $customer
 * @property string                   $title
 * @property string                   $description
 * @property string                   $type
 * @property ?string                  $group_label
 * @property ?string                  $prepaid_reference
 *
 * @mixin Builder<Invoice>
 */
class Invoice extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'invoices';

    protected $fillable = [
        'subscription_id',
        'customer_id',
        'product_id',
        'start_date',
        'end_date',
        'period',
        'gross_price',
        'net_price',
        'vat_code',
        'vat_rate',
        'ledger_code',
        'paid',
        'sent_to_harbor_at',
        'parent_invoice_id',
        'credit_reason',
        'merge_on_pdf_with_invoice_id',
        'title',
        'description',
        'group_label',
        'type',
        'prepaid_reference',
    ];

    protected $attributes = [
        'ledger_code' => 8011,
    ];

    public function isAnnounced(): bool
    {
        return $this->announced_by_harbor_at !== null;
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id', 'id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function mergeOnPdfWithInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'merge_on_pdf_with_invoice_id', 'id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function childInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id', 'id');
    }

    /**
     * @return BelongsToMany<OneTimeService, $this>
     */
    public function oneTimeServices(): BelongsToMany
    {
        return $this->belongsToMany(OneTimeService::class, 'one_time_service_invoice');
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['product.productGroup'],
        ];
    }

    protected function casts(): array
    {
        return [
            'vat_rate' => 'float',
            'sent_to_harbor_at' => 'datetime',
            'announced_by_harbor_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'credit_reason' => InvoiceLineCreditReason::class,
        ];
    }
}
