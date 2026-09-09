<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int              $id
 * @property string           $handle
 * @property string           $original_business_unit
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<DomainContact>
 */
class DomainContactAnonymousHandle extends Model implements AuditableContract
{
    use HasTimestamps;
    use Auditable;

    protected $table = 'domain_contact_anonymous_handles';

    protected $fillable = [
        'handle',
        'original_business_unit',
    ];
}
