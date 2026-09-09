<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Database\UuidCast;

/**
 * @mixin Builder<OneTimeService>
 *
 * @property int                  $id
 * @property UuidInterface        $uuid
 * @property int                  $customer_id
 * @property Customer             $customer
 * @property int                  $subscription_id
 * @property Subscription         $subscription
 * @property int                  $product_id
 * @property Product              $product
 * @property int                  $amount
 * @property int                  $discount_percentage
 * @property int                  $gross_price
 * @property CarbonImmutable      $execution_date
 * @property OneTimeServiceStatus $status
 * @property ?CarbonImmutable     $created_at
 * @property ?CarbonImmutable     $updated_at
 */
class OneTimeService extends Model
{
    protected $table = 'one_time_services';

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'one_time_service_invoice');
    }

    protected function casts(): array
    {
        return [
            'execution_date' => 'datetime',
            'status' => OneTimeServiceStatus::class,
            'uuid' => UuidCast::class,
        ];
    }
}
