<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

/**
 * @property int                              $id
 * @property ProviderType                     $type
 * @property ProviderSlug                     $slug
 * @property bool                             $enabled
 * @property bool                             $default
 * @property Pivot                            $pivot
 * @property Collection<int, ProviderSetting> $settings
 * @property ?CarbonImmutable                 $created_at
 * @property ?CarbonImmutable                 $updated_at
 *
 * @mixin Builder<Provider>
 */
class Provider extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'providers';

    /**
     * @return HasMany<ProviderSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(ProviderSetting::class);
    }

    protected function casts(): array
    {
        return [
            'type' => ProviderType::class,
            'slug' => ProviderSlug::class,
        ];
    }
}
