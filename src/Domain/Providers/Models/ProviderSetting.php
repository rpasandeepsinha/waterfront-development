<?php

declare(strict_types=1);

namespace Waterfront\Domain\Providers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;

/**
 * @property int                $id
 * @property int                $provider_id
 * @property ProviderSettingKey $key
 * @property string             $value
 * @property ?CarbonImmutable   $created_at
 * @property ?CarbonImmutable   $updated_at
 *
 * @mixin Builder<ProviderSetting>
 */
class ProviderSetting extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'provider_settings';

    protected function casts(): array
    {
        return [
            'key' => ProviderSettingKey::class,
        ];
    }
}
