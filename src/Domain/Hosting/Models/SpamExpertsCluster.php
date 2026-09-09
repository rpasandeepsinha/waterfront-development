<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int                                $id
 * @property string                             $hostname
 * @property string|null                        $business_unit
 * @property string                             $username
 * @property string                             $password
 * @property bool                               $ssl
 * @property Collection<int, HostingDeployment> $hostingDeployments
 * @property ?CarbonImmutable                   $created_at
 * @property ?CarbonImmutable                   $updated_at
 * @property ?CarbonImmutable                   $deleted_at
 *
 * @mixin Builder<SpamExpertsCluster>
 */
class SpamExpertsCluster extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use HasTimestamps;

    protected $fillable = [
        'hostname',
        'business_unit',
        'username',
        'password',
        'ssl',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return HasMany<HostingDeployment, $this>
     */
    public function hostingDeployments(): HasMany
    {
        return $this->hasMany(HostingDeployment::class);
    }

    public function getPasswordAttribute(): string
    {
        if (! array_key_exists('password', $this->attributes)) {
            // in Nova when you create a new model
            return '';
        }

        /** @var Encrypter $encrypter */
        $encrypter = Application::getInstance()->make(Encrypter::class);

        $decrypted = $encrypter->decrypt($this->attributes['password']);
        assert(is_string($decrypted));

        return $decrypted;
    }

    public function setPasswordAttribute(string $password): void
    {
        /** @var Encrypter $encrypter */
        $encrypter = Application::getInstance()->make(Encrypter::class);

        $this->attributes['password'] = $encrypter->encrypt($password);
    }
}
