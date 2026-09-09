<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                        $id
 * @property int                        $domain_business_unit_id
 * @property string                     $api_url
 * @property string                     $api_key
 * @property string                     $handle
 * @property DomainProviderBusinessUnit $domainProviderBusinessUnit
 * @property ?CarbonImmutable           $created_at
 * @property ?CarbonImmutable           $updated_at
 *
 * @mixin Builder<DomainDeployment>
 */
class RtrProviderCredentials extends Model
{
    protected $table = 'rtr_provider_credentials';

    protected $fillable = [
        'domain_business_unit_id',
        'api_url',
        'api_key',
        'handle',
    ];

    /**
     * @return BelongsTo<DomainProviderBusinessUnit, $this>
     */
    public function domainProviderBusinessUnit(): BelongsTo
    {
        return $this->belongsTo(DomainProviderBusinessUnit::class, 'domain_business_unit_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'domain_business_unit_id' => 'int',
            'api_key' => 'encrypted',
        ];
    }
}
