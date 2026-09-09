<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int                                    $id
 * @property ?string                                $last_result
 * @property ?CarbonImmutable                       $last_result_received
 * @property ?string                                $last_result_premium_provider
 * @property ?CarbonImmutable                       $last_result_premium_provider_received
 * @property Subscription                           $subscription
 * @property string                                 $subscription_uuid
 * @property Collection<int, DnsNameserver>         $dnsNameservers
 * @property Collection<int, DnsVanityNameserver>   $vanityNameservers
 * @property Collection<int, DnsExternalNameserver> $externalNameservers
 * @property ?CarbonImmutable                       $created_at
 * @property ?CarbonImmutable                       $updated_at
 * @property ?CarbonImmutable                       $deleted_at
 * @property NameserverType                         $nameserver_type
 *
 * @See docs/Domain/DNS/README.md#Nameservers
 *
 * @mixin Builder<DnsDeployment>
 */
class DnsDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'dns_deployments';

    protected $fillable = [
        'subscription_uuid',
        'last_result_received',
        'last_result',
        'last_result_premium_provider_received',
        'last_result_premium_provider',
        'nameserver_type',
    ];

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsToMany<DnsNameserver, $this>
     */
    public function dnsNameservers(): BelongsToMany
    {
        return $this->belongsToMany(
            DnsNameserver::class,
            'dns_deployment_dns_nameserver',
            'dns_deployment_id',
            'dns_nameserver_id',
        );
    }

    /** @return BelongsToMany<DnsVanityNameserver, $this> */
    public function vanityNameservers(): BelongsToMany
    {
        return $this->belongsToMany(DnsVanityNameserver::class)->withTimestamps();
    }

    /** @return HasMany<DnsExternalNameserver, $this> */
    public function externalNameservers(): HasMany
    {
        return $this->hasMany(DnsExternalNameserver::class);
    }

    protected function casts(): array
    {
        return [
            'last_result_received' => 'datetime',
            'last_result_premium_provider_received' => 'datetime',
            'nameserver_type' => NameserverType::class,
        ];
    }
}
