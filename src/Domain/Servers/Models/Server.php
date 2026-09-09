<?php

declare(strict_types=1);

namespace Waterfront\Domain\Servers\Models;

use Carbon\CarbonImmutable;
use ErrorException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use RuntimeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\DirectAdminClient\Connection\DirectAdminServer;

/**
 * @mixin Builder<Server>
 *
 * @property int                                $id
 * @property ?string                            $customer_uuid
 * @property string                             $hostname
 * @property bool                               $customer_login_as_admin
 * @property ?string                            $domain
 * @property ?string                            $ipv4
 * @property ?string                            $ipv6
 * @property bool                               $allow_new_websites
 * @property ?string                            $login
 * @property ?string                            $loginkey
 * @property ?string                            $name
 * @property ?string                            $owner
 * @property string|null                        $password
 * @property string                             $php_version
 * @property ?string                            $endpoint
 * @property ?int                               $port
 * @property ?string                            $secret_key
 * @property ServerType                         $type
 * @property ?bool                              $use_ssl
 * @property ?string                            $username
 * @property ?int                               $maximum_websites
 * @property ?CarbonImmutable                   $created_at
 * @property ?CarbonImmutable                   $updated_at
 * @property ?CarbonImmutable                   $deleted_at
 * @property Collection<int, HostingDeployment> $hostingDeployments
 * @property-read int                           $available_websites
 * @property-read int                           $number_of_websites
 * @property-read ?int                          $subscriptions_count
 */
class Server extends Model implements AuditableContract, DirectAdminServer
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'hosting_servers';

    protected $fillable = [
        'customer_login_as_admin',
        'domain',
        'hostname',
        'ipv4',
        'ipv6',
        'login',
        'loginkey',
        'name',
        'owner',
        'password',
        'php_version',
        'port',
        'secret_key',
        'type',
        'use_ssl',
        'username',
    ];

    public function getPortAttribute(?int $value = null): ?int
    {
        if ($value !== null) {
            return $value;
        }

        if ($this->type === ServerType::DIRECTADMIN) {
            return null;
        }

        $pleskPort = Config::get('hostingservice.plesk.port');
        assert(is_string($pleskPort) || is_int($pleskPort));

        return intval($pleskPort);
    }

    public function getApiUrlAttribute(): string
    {
        $protocol = $this->use_ssl === null || ! $this->use_ssl
            ? 'http://'
            : 'https://';

        $serverUrl = $protocol . $this->hostname . ':' . $this->port;

        if ($this->endpoint === null) {
            return $serverUrl;
        }

        return $serverUrl . $this->endpoint;
    }

    /**
     * @return HasMany<HostingDeployment, $this>
     */
    public function hostingDeployments(): HasMany
    {
        return $this->hasMany(HostingDeployment::class);
    }

    /**
     * @return HasMany<ResellerHostingDeployment, $this>
     */
    public function resellerHostingDeployments(): HasMany
    {
        return $this->hasMany(ResellerHostingDeployment::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_uuid', 'uuid');
    }

    public function getNumberOfWebsitesAttribute(): int
    {
        return $this->hostingDeployments()->whereHas('subscription', function (Builder $query): void {
            $query->whereNot('administrative_status', AdministrativeStatus::ARCHIVED->value);
        })->count();
    }

    /**
     * Count the amount of free subscriptions on this server.
     */
    public function getAvailableWebsitesAttribute(): int
    {
        $maxSites = $this->maximum_websites;

        if (is_null($maxSites)) {
            return 9999;
        }

        return $maxSites - $this->number_of_websites;
    }

    public function getUsername(): string
    {
        return $this->username ?? '';
    }

    /**
     * Boolean if the Direct Admin Server is available over SSL or not.
     */
    public function usesSsl(): bool
    {
        return $this->use_ssl ?? false;
    }

    /**
     * Get the domain / ip of the Direct Admin Server to connect to.
     */
    public function getDomain(): string
    {
        return $this->domain ?? '';
    }

    /**
     * Get the port of the Direct Admin Server to connect to.
     */
    public function getPort(): int
    {
        return $this->port ?? 0;
    }

    public function getIpv4(): ?string
    {
        return $this->ipv4;
    }

    public function getIpv6(): ?string
    {
        return $this->ipv6;
    }

    public function getPhpVersion(): ?string
    {
        return $this->php_version;
    }

    public function getProviderSlug(): ?ProviderSlug
    {
        $hostingDeployment = $this->hostingDeployments()->first();

        if (! $hostingDeployment instanceof HostingDeployment) {
            return Provider::where('default', true)->where('type', ProviderType::HOSTING)->firstOrFail()->slug;
        }

        $slug = $hostingDeployment->provider?->slug;
        if ($slug !== null) {
            return $slug;
        }

        throw new RuntimeException(
            "The hosting deployment was not coupled with a provider backend!
            hosting deployment id: {$hostingDeployment->id}"
        );
    }

    public function getSubscriptionsCountAttribute(): int
    {
        return $this->getNumberOfWebsitesAttribute();
    }

    public function getLoginKey(): string
    {
        return $this->getLoginKeyAttribute() ?? '';
    }

    public function getLoginKeyAttribute(): string|null
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);
        try {
            $decrypted = $encrypter->decrypt($this->attributes['loginkey'] ?? '');
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (ErrorException) {
            $decrypted = $encrypter->decrypt($this->attributes['loginkey'] ?? '', false);
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (DecryptException) {
            return $this->attributes['loginkey'] ?? '';
        }
    }

    public function setLoginKey(string|null $loginKey): void
    {
        $this->setLoginKeyAttribute($loginKey);
    }

    /**
     * Encypt the login key.
     */
    public function setLoginKeyAttribute(string|null $loginKey): void
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);

        $this->attributes['loginkey'] = ($loginKey !== null) ? $encrypter->encrypt($loginKey) : $loginKey;
    }

    public function getSecretKey(): string
    {
        return $this->getSecretKeyAttribute() ?? '';
    }

    public function getSecretKeyAttribute(): string|null
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);

        try {
            $decrypted = $encrypter->decrypt($this->attributes['secret_key'] ?? '');
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (ErrorException) {
            $decrypted = $encrypter->decrypt($this->attributes['secret_key'] ?? '', false);
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (DecryptException) {
            return $this->attributes['secret_key'] ?? '';
        }
    }

    public function setSecretKey(string|null $secret_key): void
    {
        $this->setSecretKeyAttribute($secret_key);
    }

    public function setSecretKeyAttribute(string|null $secret_key): void
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);

        $this->attributes['secret_key'] = ($secret_key !== null) ? $encrypter->encrypt($secret_key) : $secret_key;
    }

    public function getPassword(): string
    {
        return $this->getPasswordAttribute() ?? '';
    }

    public function getPasswordAttribute(): string|null
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);

        try {
            $decrypted = $encrypter->decrypt($this->attributes['password'] ?? '');
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (ErrorException) {
            $decrypted = $encrypter->decrypt($this->attributes['password'] ?? '', false);
            assert(is_string($decrypted) || is_null($decrypted));

            return $decrypted;
        } catch (DecryptException) {
            return $this->attributes['password'] ?? '';
        }
    }

    public function setPassword(string|null $password): void
    {
        $this->setPasswordAttribute($password);
    }

    public function setPasswordAttribute(string|null $password): void
    {
        /** @var Encrypter $encrypter */
        $encrypter = Container::getInstance()->make(Encrypter::class);

        $this->attributes['password'] = ($password !== null) ? $encrypter->encrypt($password) : $password;
    }

    protected static function booted(): void
    {
        static::creating(function ($server): void {
            if (is_null($server->hostname) && ! is_null($server->domain)) {
                $server->hostname = $server->domain;
            }
            if (is_null($server->domain) && ! is_null($server->hostname)) {
                $server->domain = $server->hostname;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'use_ssl' => 'boolean',
            'allow_new_websites' => 'boolean',
            'maximum_websites' => 'int',
            'type' => ServerType::class,
        ];
    }
}
