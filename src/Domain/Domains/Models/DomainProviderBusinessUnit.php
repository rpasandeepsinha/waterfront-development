<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int              $id
 * @property string           $slug
 * @property string           $name
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<DomainDeployment>
 */
class DomainProviderBusinessUnit extends Model
{
    protected $table = 'domain_provider_business_unit';

    protected $fillable = [
        'slug',
        'name',
    ];

    /**
     * @return HasMany<DomainDeployment, $this>
     */
    public function domainDeployments(): HasMany
    {
        return $this->hasMany(DomainDeployment::class, 'domain_business_unit_id');
    }
}
