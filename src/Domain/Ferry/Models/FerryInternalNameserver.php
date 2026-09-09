<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $nameserver_hostname
 */
class FerryInternalNameserver extends Model implements Auditable
{
    use AuditableTrait;
    use HasTimestamps;

    protected $table = 'migrated_dns_internal_nameservers';

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
