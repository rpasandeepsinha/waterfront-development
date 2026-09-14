<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int              $id
 * @property int              $customer_id
 * @property string           $street_name
 * @property string           $street_number
 * @property ?string          $street_number_addition
 * @property string           $zip_code
 * @property string           $city
 * @property string           $country_code
 * @property string           $address_line
 * @property ?string          $type
 * @property Customer         $customer
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<CustomerAddress>
 */
class CustomerAddress extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'customer_addresses';

    protected $fillable = [
        'street_name',
        'street_number',
        'street_number_addition',
        'zip_code',
        'city',
        'country_code',
        'type',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Get the street name and number as a single line. */
    public function getAddressLineAttribute(): string
    {
        return trim(trim($this->street_name) . ' ' . trim($this->street_number));
    }

    public function getStreetNumberLetters(): ?string
    {
        return preg_replace('/[0-9]/', '', $this->street_number) !== ''
            ? preg_replace('/[0-9]/', '', $this->street_number)
            : null;
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['customer'],
            'App\Models\CustomerAddress' => ['customer'],
            'Modules\Customer\Models\CustomerAddress' => ['customer'],
        ];
    }
}
