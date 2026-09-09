<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;
use Waterfront\Domain\Payments\Models\Mandate;

/**
 * @property int                                   $id
 * @property string                                $reference_customer_number
 * @property string                                $reference_name
 * @property string                                $group_type
 * @property bool                                  $enable_invoicing
 * @property bool                                  $successful
 * @property bool                                  $administrative_successful
 * @property bool                                  $technical_successful
 * @property bool                                  $billing_successful
 * @property bool                                  $dns_successful
 * @property ?CarbonImmutable                      $migrated_at
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 * @property Collection<int, Customer>             $customers
 * @property Collection<int, MigratedSubscription> $migratedSubscriptions
 * @property Collection<int, Mandate>              $mandates
 * @property Collection<int, MigratedDnsTemplate>  $migratedDnsTemplates
 *
 * @mixin Builder<MigratedCustomer>
 */
class MigratedCustomer extends Model
{
    protected $table = 'migrated_customers';

    protected $fillable = [
        'reference_customer_number',
        'reference_name',
        'group_type',
        'successful',
        'enable_invoicing',
        'administrative_successful',
        'technical_successful',
        'billing_successful',
        'dns_successful',
        'migrated_at',
    ];

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    /**
     * @return BelongsToMany<MigratedSubscription, $this>
     */
    public function migratedSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(MigratedSubscription::class);
    }

    /**
     * @return BelongsToMany<Mandate, $this>
     */
    public function mandates(): BelongsToMany
    {
        return $this->belongsToMany(Mandate::class)->withTimestamps();
    }

    /**
     * @return HasMany<MigratedDnsTemplate, $this>
     */
    public function migratedDnsTemplates(): HasMany
    {
        return $this->hasMany(MigratedDnsTemplate::class);
    }

    protected function casts(): array
    {
        return [
            'administrative_successful' => 'boolean',
            'technical_successful' => 'boolean',
            'billing_successful' => 'boolean',
            'dns_successful' => 'boolean',
            'successful' => 'boolean',
            'enable_invoicing' => 'boolean',
            'customer_id' => 'int',
            'migrated_at' => 'datetime',
        ];
    }
}
