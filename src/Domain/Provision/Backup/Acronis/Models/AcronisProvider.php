<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Acronis\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int              $id
 * @property UuidInterface    $uuid
 * @property string           $name
 * @property string           $endpoint
 * @property UuidInterface    $tenant_uuid
 * @property UuidInterface    $client_id
 * @property string           $client_secret
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property bool             $default
 * @property string           $sso_target_url
 */
class AcronisProvider extends Model
{
    protected $table = 'acronis_providers';

    protected function casts(): array
    {
        return [
            'uuid' => UuidCast::class,
            'tenant_uuid' => UuidCast::class,
            'client_id' => UuidCast::class,
            'client_secret' => 'encrypted',
        ];
    }
}
