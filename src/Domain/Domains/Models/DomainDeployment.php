<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Models;

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
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;

/**
 * @property int                                   $id
 * @property string                                $subscription_uuid
 * @property int                                   $provider_id
 * @property Subscription                          $subscription
 * @property DomainContact|null                    $contactOwner
 * @property Provider                              $provider
 * @property ?DnsCustomerTemplate                  $template
 * @property Collection<int, DomainProviderStatus> $domainProviderStatus
 * @property ?int                                  $template_id
 * @property ?RtrDomainStatus                      $domain_status
 * @property ?string                               $last_result
 * @property ?CarbonImmutable                      $last_result_received
 * @property int|null                              $contact_owner_id
 * @property bool                                  $dnssec_enabled
 * @property bool                                  $private_whois_enabled
 * @property ?string                               $transfer_secret
 * @property Collection<int, DnsNameserver>        $dnsNameservers
 * @property ?int                                  $domain_business_unit_id
 * @property ?DomainProviderBusinessUnit           $businessUnit
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 * @property ?CarbonImmutable                      $deleted_at
 *
 * @mixin Builder<DomainDeployment>
 */
class DomainDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'domain_deployments';

    protected $fillable = [
        'subscription_uuid',
        'provider_id',
        'template_id',
        'domain_status',
        'last_result',
        'last_result_received',
        'contact_owner_id',
        'dnssec_enabled',
        'private_whois_enabled',
        'transfer_secret',
        'domain_business_unit_id',
    ];

    /**
     * @return BelongsTo<DomainContact, $this>
     */
    public function contactOwner(): BelongsTo
    {
        return $this->belongsTo(DomainContact::class, 'contact_owner_id', 'id');
    }

    /**
     * @return BelongsTo<DomainProviderBusinessUnit, $this>
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(DomainProviderBusinessUnit::class, 'domain_business_unit_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'id');
    }

    /**
     * @return BelongsTo<DnsCustomerTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(DnsCustomerTemplate::class, 'template_id', 'id');
    }

    /**
     * @return HasMany<DomainProviderStatus, $this>
     */
    public function domainProviderStatus(): HasMany
    {
        return $this->hasMany(DomainProviderStatus::class);
    }

    /**
     * @return BelongsToMany<DnsNameserver, $this>
     */
    public function dnsNameservers(): BelongsToMany
    {
        return $this->belongsToMany(
            DnsNameserver::class,
            'dns_nameserver_domain_deployments',
            'domain_deployment_id',
            'dns_nameserver_id',
        );
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            'Modules\DomainService\Models\Subscription' => ['subscription.product.productGroup'],
            self::class => ['subscription.product.productGroup'],
        ];
    }

    protected function casts(): array
    {
        return [
            'provider_id' => 'int',
            'template_id' => 'int',
            'domain_status' => RtrDomainStatus::class,
            'contact_owner_id' => 'int',
            'last_result_received' => 'datetime',
        ];
    }
}
