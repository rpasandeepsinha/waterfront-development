<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Models\ProductDiscount;

/**
 * Used for the hasOneThrough relation on the customer.
 *
 * @property int              $id
 * @property int              $customer_id
 * @property int              $product_discount_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property Customer         $customer
 * @property Invoice          $invoice
 *
 * @mixin Builder<CustomerProductDiscount>
 */
class CustomerProductDiscount extends Model
{
    protected $fillable = [
        'customer_id',
        'product_discount_id',
    ];

    protected $table = 'customer_product_discount';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ProductDiscount, $this>
     */
    public function productDiscount(): BelongsTo
    {
        return $this->belongsTo(ProductDiscount::class);
    }
}
