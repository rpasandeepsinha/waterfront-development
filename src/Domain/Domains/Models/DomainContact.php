<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Traits\HasPhoneNumber;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;

/**
 * @property int                               $id
 * @property string                            $uuid
 * @property string                            $email
 * @property string                            $first_name
 * @property string                            $last_name
 * @property string                            $phone_country_code
 * @property string                            $phone_area_code
 * @property string                            $phone_subscriber_number
 * @property string                            $street_name
 * @property string                            $street_number
 * @property string                            $zip_code
 * @property string                            $city
 * @property string                            $country_code
 * @property string|null                       $organization
 * @property bool                              $default_owner
 * @property int                               $customer_id
 * @property Pivot                             $pivot
 * @property Collection<int, DomainDeployment> $contactOwnerDomainSubscriptions
 * @property Collection<int, Provider>         $providers
 * @property Customer                          $customer
 * @property ?CarbonImmutable                  $created_at
 * @property ?CarbonImmutable                  $updated_at
 * @property ?CarbonImmutable                  $deleted_at
 * @property-read bool $has_anonymous_handle
 *
 * @mixin Builder<DomainContact>
 */
class DomainContact extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use HasPhoneNumber;

    protected $table = 'domain_contacts';

    protected $fillable = [
        'uuid',
        'email',
        'first_name',
        'last_name',
        'phone_country_code',
        'phone_area_code',
        'phone_subscriber_number',
        'street_name',
        'street_number',
        'zip_code',
        'city',
        'country_code',
        'organization',
        'default_owner',
        'customer_id',
    ];

    protected $appends = ['phone_number'];

    public function getAddressAttribute(): CustomerAddress
    {
        return new CustomerAddress([
            'street_name' => $this->street_name,
            'street_number' => $this->street_number,
            'zip_code' => $this->zip_code,
            'city' => $this->city,
            'country_code' => $this->country_code,
        ]);
    }

    public function getHasAnonymousHandleAttribute(): bool
    {
        return $this->providers()
            ->whereNot('slug', ProviderSlug::PLACEHOLDER)
            ->wherePivotIn(
                'external_contact',
                DomainContactAnonymousHandle::pluck('handle'),
            )
            ->exists();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<DomainDeployment, $this> */
    public function contactOwnerDomainSubscriptions(): HasMany
    {
        return $this->hasMany(DomainDeployment::class, 'contact_owner_id');
    }

    /** @return BelongsToMany<Provider, $this> */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class)
            ->withPivot(['external_contact', 'domain_business_unit_id'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Show array in the same format as the customer.
     *
     * @return mixed[]
     */
    public function domainContactArray(): array
    {
        return [
            'address' => [
                'street_name' => $this->street_name,
                'street_number' => $this->street_number,
                'zip_code' => $this->zip_code,
                'city' => $this->city,
                'country_code' => $this->country_code,
            ],
            'organization' => $this->organization,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->customer->gender,
            'phone_country_code' => $this->phone_country_code,
            'phone_area_code' => $this->phone_area_code,
            'phone_subscriber_number' => $this->phone_subscriber_number,
            'email' => $this->email,
            'locale' => $this->customer->locale,
            'customer_number' => $this->customer->customer_number,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (DomainContact $contact): void {
            $contact->default_owner = static::where('customer_id', $contact->customer_id)
                ->where('default_owner', true)
                ->exists()
                ? false
                : true;

            $contact->uuid = (string) Uuid::uuid4();
        });
    }

    protected function casts(): array
    {
        return [
            'default_owner' => 'boolean',
            'customer_id' => 'int',
        ];
    }
}
