<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Enums\CustomerContactType;

/**
 * @property int              $id
 * @property int              $customer_id
 * @property string           $first_name
 * @property string           $uuid
 * @property string           $last_name
 * @property string|null      $company
 * @property string           $email
 * @property string           $type
 * @property Customer         $customer
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<CustomerContact>
 */
#[RouteKey('uuid')]
class CustomerContact extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'company',
        'email',
        'type',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (CustomerContact $customerContact): void {
            $customerContact->uuid = Str::uuid()->toString();
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getNameAttribute(): string
    {
        return trim(trim($this->first_name) . ' ' . trim($this->last_name));
    }

    public function setTypeAttribute(string $type): void
    {
        $this->attributes['type'] = CustomerContactType::from($type)->value;
    }

    protected function casts(): array
    {
        return [
            'customer_id' => 'int',
        ];
    }
}
