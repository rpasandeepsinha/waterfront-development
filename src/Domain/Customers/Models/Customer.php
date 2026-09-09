<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\Access\Authorizable;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\QueryBuilders\CustomerQueryBuilder;
use Waterfront\Domain\Customers\Traits\HasPhoneNumber;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Mailer\IsMailable;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Domain\Payments\Models\Payment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Support\Database\UuidCast;
use Waterfront\Support\Exceptions\NotImplementedException;

/**
 * @property int                                       $id
 * @property int                                       $customer_number
 * @property int                                       $terms_of_payment
 * @property UuidInterface                             $uuid
 * @property int                                       $credit_limit
 * @property ?CustomerAddress                          $address
 * @property Collection<int, CustomerVatError>         $vatErrors
 * @property string                                    $first_name
 * @property string                                    $last_name
 * @property ?string                                   $organization
 * @property ?string                                   $department
 * @property string                                    $phone_country_code
 * @property string                                    $phone_number
 * @property string                                    $phone_area_code
 * @property string                                    $phone_subscriber_number
 * @property ?string                                   $purchase_reference
 * @property ?string                                   $invoice_history_url
 * @property ?string                                   $admin_url
 * @property string                                    $name
 * @property string                                    $contact_name
 * @property ?string                                   $vat_number
 * @property ?string                                   $coc_number
 * @property ?float                                    $vat_rate
 * @property bool                                      $icp
 * @property string                                    $email
 * @property string                                    $locale
 * @property string                                    $gender
 * @property bool                                      $is_verified
 * @property PaymentType                               $payment_type
 * @property bool                                      $has_direct_debit
 * @property bool                                      $is_abuse
 * @property bool                                      $terms_accepted
 * @property Collection<int, Label>                    $labels
 * @property Collection<int, Transfer>                 $fromCustomerTransfers
 * @property Collection<int, Transfer>                 $toCustomerTransfers
 * @property ?ProductDiscount                          $product_discount
 * @property Collection<int, ProductDiscount>          $productDiscounts
 * @property Collection<int, Subscription>             $subscriptions
 * @property Collection<int, ProductGroup>             $productGroups
 * @property Collection<int, DomainContact>            $domainContacts
 * @property ?CarbonImmutable                          $anonymized_at
 * @property ?CarbonImmutable                          $data_last_confirmed_at
 * @property CarbonImmutable                           $customer_since
 * @property ?CarbonImmutable                          $created_at
 * @property ?CarbonImmutable                          $updated_at
 * @property Collection<int, CustomerContact>          $customerContacts
 * @property ?CustomerContact                          $financialContact
 * @property Collection<int, Microsoft365CustomerInfo> $microsoft365CustomerInfos
 * @property Collection<int, MigratedCustomer>         $migratedCustomers
 * @property Collection<int, Notes>                    $notes
 * @property ?CustomerWallet                           $wallet
 * @property Collection<int, Invoice>                  $invoices
 * @property Collection<int, EmailHistory>             $emailHistory
 * @property ?MollieCustomer                           $mollieCustomer
 * @property Collection<int, OneTimeService>           $oneTimeServices
 * @property ?Pivot                                    $pivot
 *
 * @mixin Builder<Customer>
 *
 * @method static CustomerQueryBuilder search($value)
 */
class Customer extends Model implements AuditableContract, IsMailable, Authenticatable
{
    use Auditable;
    use HasPhoneNumber;
    use Authorizable;

    public const int CREDIT_LIMIT = 100000;
    public const string DEFAULT_LOCALE = Locale::DUTCH->value;

    protected $fillable = [
        'uuid',
        'organization',
        'department',
        'first_name',
        'last_name',
        'gender',
        'phone_country_code',
        'phone_area_code',
        'phone_subscriber_number',
        'email',
        'locale',
        'terms_accepted',
        'icp',
        'coc_number',
        'vat_rate',
        'vat_number',
        'purchase_reference',
        'invoice_history_url',
        'admin_url',
        'terms_of_payment',
        'credit_limit',
        'anonymized_at',
        'data_last_confirmed_at',
    ];

    protected $attributes = [
        'credit_limit'     => Customer::CREDIT_LIMIT,
        'is_verified'     => false,
        'has_direct_debit' => false,
        'is_abuse' => false,
        'payment_type'     => PaymentType::DIRECT,
        'locale' => Customer::DEFAULT_LOCALE,
        'gender' => 'X',
    ];

    protected $appends = ['name', 'phone_number'];

    public static function boot(): void
    {
        parent::boot();

        Customer::creating(
            function (self $model): void {
                if (! array_key_exists('uuid', $model->attributes)) {
                    $model->uuid = Uuid::uuid4();
                }

                // Enforce that we will not send our own customer number to the DB
                unset($model->customer_number);
            }
        );

        Customer::created(
            function (self $model): void {
                /*
                 * Since before submitting the model to the database it will be in a state
                 * without customer number available.
                 *
                 * To experience a natural flow with customer entities we will manually set the customer number on
                 * the customer model after creating to ensure this field is present.
                 */
                /** @var Customer $freshCustomer */
                $freshCustomer = $model->fresh();

                $model->customer_number = $freshCustomer->customer_number;
            }
        );
    }

    public function setVatNumberAttribute(null|string $vatNumber): void
    {
        if ($vatNumber === null) {
            $this->attributes['vat_number'] = $vatNumber;
            return;
        }

        $this->attributes['vat_number'] = strtoupper($vatNumber);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * This relation is only used in Nova to display the subscriptions that are free
     * or have a product from the product group extension.
     *
     * @return HasMany<Subscription, $this>
     */
    public function paidOrExtensionSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)
            ->where(
                function (Builder|HasMany $filter) {
                    $filter->where(function (Builder|HasMany $priceQuery) {
                        $priceQuery
                            ->where('gross_price', '!=', 0);
                    })
                    ->orWhereHas(
                        'product.productGroup',
                        fn (Builder $productGroupQuery): Builder => $productGroupQuery->where('slug', ProductGroupType::EXTENSION)
                    );
                }
            );
    }

    /** @return HasMany<Subscription, $this> */
    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function address(): HasOne
    {
        return $this->hasOne(CustomerAddress::class);
    }

    /** @return HasMany<CustomerVatError, $this> */
    public function vatErrors(): HasMany
    {
        return $this->hasMany(CustomerVatError::class);
    }

    /** @return BelongsToMany<ProductGroup, $this> */
    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class)->withPivot('discount');
    }

    /**
     * Defines the relation with the product discount. (used for display in nova).
     *
     * @return HasOneThrough<ProductDiscount, CustomerProductDiscount, $this>
     */
    public function productDiscount(): HasOneThrough
    {
        return $this->hasOneThrough(
            ProductDiscount::class,
            CustomerProductDiscount::class,
            'customer_id',
            'id',
            'id',
            'product_discount_id'
        );
    }

    /** @return BelongsToMany<ProductDiscount, $this> */
    public function productDiscounts(): BelongsToMany
    {
        return $this->belongsToMany(ProductDiscount::class)->withTimestamps();
    }

    /** @return HasMany<CustomerContact, $this> */
    public function customerContacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /** @return HasOne<CustomerContact, $this> */
    public function financialContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)
            ->where('type', CustomerContactType::FINANCIAL->value);
    }

    /** @return HasMany<DomainContact, $this> */
    public function domainContacts(): HasMany
    {
        return $this->hasMany(DomainContact::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_uuid', 'uuid');
    }

    /** @return HasOne<MollieCustomer, $this> */
    public function mollieCustomer(): HasOne
    {
        return $this->hasOne(MollieCustomer::class);
    }

    /** @return HasMany<Transfer, $this> */
    public function fromCustomerTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'from_customer_id');
    }

    /** @return HasMany<Transfer, $this> */
    public function toCustomerTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'to_customer_id');
    }

    /** @return HasMany<Notes, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Notes::class, 'customer_id', 'id');
    }

    /** @return HasMany<Label, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function getContactNameAttribute(): string
    {
        return trim(trim($this->first_name) . ' ' . trim($this->last_name));
    }

    public function getNameAttribute(): string
    {
        if ($this->organization !== null) {
            return $this->organization;
        }

        return $this->contact_name;
    }

    public function customer(): Customer
    {
        return $this;
    }

    /**
     * @return BelongsToMany<MigratedCustomer, $this>
     */
    public function migratedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(MigratedCustomer::class);
    }

    public function newEloquentBuilder($query): CustomerQueryBuilder
    {
        return new CustomerQueryBuilder($query);
    }

    /** @return HasMany<Microsoft365CustomerInfo, $this> */
    public function microsoft365CustomerInfo(): HasMany
    {
        return $this->hasMany(Microsoft365CustomerInfo::class);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getAuthIdentifierName(): string
    {
        return 'uuid';
    }

    public function getAuthIdentifier(): string
    {
        return $this->uuid->toString();
    }

    /**
     * This method is required to implement the Authenticatable interface.
     * But shouldn't actually be called or used, therefore we throw a NotImplementedException.
     */
    public function getAuthPassword(): string
    {
        throw new NotImplementedException('The Customer model does not support password methods');
    }

    /**
     * This method is required to implement the Authenticatable interface.
     * But shouldn't actually be called or used, therefore we throw a NotImplementedException.
     */
    public function getAuthPasswordName(): string
    {
        throw new NotImplementedException('The Customer model does not support password methods');
    }

    /**
     * This method is required to implement the Authenticatable interface.
     * But shouldn't actually be called or used, therefore we throw a NotImplementedException.
     */
    public function getRememberToken(): string
    {
        throw new NotImplementedException('The Customer model does not support remember token methods');
    }

    /**
     * This method is required to implement the Authenticatable interface.
     * But shouldn't actually be called or used, therefore we throw a NotImplementedException.
     */
    public function setRememberToken($value): void
    {
        throw new NotImplementedException('The Customer model does not support remember token methods');
    }

    /**
     * This method is required to implement the Authenticatable interface.
     * But shouldn't actually be called or used, therefore we throw a NotImplementedException.
     */
    public function getRememberTokenName(): string
    {
        throw new NotImplementedException('The Customer model does not support remember token methods');
    }

    /** @return HasOne<CustomerWallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }

    public function isCompany(): bool
    {
        return ! is_null($this->organization) && ! is_null($this->vat_number) && ! is_null($this->coc_number);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<EmailHistory, $this> */
    public function EmailHistory(): HasMany
    {
        return $this->hasMany(EmailHistory::class);
    }

    /** @return HasMany<OneTimeService, $this> */
    public function oneTimeServices(): HasMany
    {
        return $this->hasMany(OneTimeService::class);
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->uuid;
    }

    protected function casts(): array
    {
        return [
            'customer_number' => 'int',
            'icp' => 'bool',
            'is_verified' => 'bool',
            'terms_accepted' => 'bool',
            'has_direct_debit' => 'bool',
            'is_abuse' => 'bool',
            'vat_rate' => 'float',
            'terms_of_payment' => 'int',
            'anonymized_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'data_last_confirmed_at' => 'datetime',
            'customer_since' => 'date',
            'uuid' => UuidCast::class,
            'payment_type' => PaymentType::class,
        ];
    }
}
