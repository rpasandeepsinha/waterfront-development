<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property-read string $external_customer_id
 * @property-read int $waterfront_customer_id
 * @property-read int $id
 * @property-read string $waterfront_customer_number
 * @property-read bool $enabled_invoicing
 * @property-read string $batch_group
 * @property-read string $reference_name
 * @property-read string $email
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $organization
 * @property-read string|null $coc_number
 * @property-read string|null $vat_number
 * @property-read int|null $vat_rate
 * @property-read string $country_code
 * @property-read string|null $phone_country_code
 * @property-read string|null $phone_area_code
 * @property-read string|null $phone_subscriber_number
 * @property-read int $wallet_id
 * @property-read int $wallet_amount
 * @property-read string $refund_requested_at
 * @property-read string $legacy_subscription_id
 * @property-read int $subscription_id
 * @property-read string|null $domain
 * @property-read string $product_group
 * @property-read string $product
 * @property-read int $gross_price
 * @property-read int $net_price
 * @property-read string $administrative_status
 * @property-read string $technical_status
 * @property-read string $subscription_last_updated
 * @property-read string $renewal_date
 * @property-read string $cancel_date
 * @property-read string $next_billing_date
 * @property-read string $technical_driver
 * @property-read string|null $server_hostname
 * @property-read int|null $domain_contact_handle_id
 * @property-read string|null $waterfront_nameservers
 * @property-read Subscription $subscription
 * @property-read Customer $customer
 * @property-read Product $productModel
 *
 * @mixin Builder<MigrationState>
 */
class MigrationState extends Model
{
    protected $table = 'migration_state';

    public static function boot()
    {
        parent::boot();

        static::creating(fn ($model) => false);

        static::saving(fn ($model) => false);

        static::deleting(fn ($model) => false);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'waterfront_customer_id');
    }

    /** @return BelongsToMany<MigratedCustomer, $this> */
    public function migratedCustomer(): BelongsToMany
    {
        return $this->belongsToMany(MigratedCustomer::class, 'external_customer_id');
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /** @return BelongsToMany<MigratedSubscription, $this> */
    public function migratedSubscription(): BelongsToMany
    {
        return $this->belongsToMany(MigratedSubscription::class, 'legacy_subscription_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function productModel(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product', 'slug');
    }
}
