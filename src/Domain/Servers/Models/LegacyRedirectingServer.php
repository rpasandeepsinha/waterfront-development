<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int              $id
 * @property string           $hostname
 * @property string           $ipv4
 * @property string|null      $ipv6
 * @property string           $original_business_unit
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<LegacyRedirectingServer>
 */
class LegacyRedirectingServer extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'hosting_redirecting_legacy_servers';

    protected $fillable = [
        'hostname',
        'ipv4',
        'ipv6',
        'original_business_unit',
    ];
}
