<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Waterfront\Domain\Products\Models\Product;

/**
 * @property int                                      $id
 * @property string                                   $slug
 * @property string                                   $name
 * @property string                                   $api_url
 * @property string                                   $ui_url
 * @property string                                   $domain_id
 * @property string                                   $domain_name
 * @property bool                                     $preferred               Use as preferred environment for new ManagerDomains
 * @property string                                   $default_email_address   Use as default for new accounts/users
 * @property string                                   $default_role_id         Create accounts with this role id
 * @property ?string                                  $last_processed_event_id Last processed CloudStack event id
 * @property ?CarbonImmutable                         $last_processed_event_at Last processed CloudStack event time
 * @property Collection<int, ManagerDomainDeployment> $managerDomainDeployment
 * @property Product[]                                $products
 * @property ?string                                  $api_key
 * @property ?string                                  $secret_key
 * @property ?CarbonImmutable                         $created_at
 * @property ?CarbonImmutable                         $updated_at
 *
 * @mixin Builder<Environment>
 */
class Environment extends Model
{
    protected $table = 'cloudstack_environments';

    protected $fillable = [
        'slug',
        'name',
        'api_url',
        'ui_url',
        'domain_id',
        'domain_name',
        'preferred',
        'default_email_address',
        'default_role_id',
        'api_key',
        'secret_key',
    ];

    protected $hidden = [
        'secret_key',
    ];

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'cloudstack_environment_products')->withPivot([
            'product_identifier',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * @return HasMany<ManagerDomainDeployment, $this>
     */
    public function managerDomainDeployments(): HasMany
    {
        return $this->hasMany(ManagerDomainDeployment::class);
    }

    protected function casts(): array
    {
        return [
            'last_processed_event_at' => 'datetime',
            'preferred' => 'bool',
            'api_key' => 'encrypted',
            'secret_key' => 'encrypted',
        ];
    }
}
