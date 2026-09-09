<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\HostingDeploymentInterface;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Exceptions\ResellerHostingException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int              $id
 * @property int|null         $server_id
 * @property string           $subscription_uuid
 * @property int              $provider_id
 * @property string|null      $directadmin_customer_username
 * @property string|null      $plesk_customer_username
 * @property int|null         $plesk_customer_id
 * @property string           $storage_type
 * @property int|null         $disk_space
 * @property int|null         $max_users
 * @property int|null         $max_domains
 * @property int|null         $max_email_addresses
 * @property int|null         $max_traffic
 * @property int|null         $max_databases
 * @property string|null      $permissions
 * @property string|null      $last_created_result
 * @property ?CarbonImmutable $last_created_result_received
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 * @property-read Collection<int, Audit> $audits
 * @property-read int|null $audits_count
 * @property-read string|null $relevant_username
 * @property-read Subscription $subscription
 * @property-read Provider $provider
 * @property-read Server|null $server
 *
 * @mixin Builder<ResellerHostingDeployment>
 */
class ResellerHostingDeployment extends Model implements AuditableContract, HostingDeploymentInterface
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'reseller_hosting_deployments';

    protected $fillable = [
        'subscription_uuid',
        'provider_id',
        'directadmin_customer_username',
        'plesk_customer_username',
        'plesk_customer_id',
        'storage_type',
        'disk_space',
        'max_users',
        'max_domains',
        'max_email_addresses',
        'max_traffic',
        'max_databases',
        'last_created_result',
        'last_created_result_received',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id', 'id');
    }

    /**
     * @throws ResellerHostingException
     */
    public function getRelevantUsernameAttribute(): ?string
    {
        return match ($this->provider->slug) {
            ProviderSlug::PLESK => $this->plesk_customer_username,
            ProviderSlug::DIRECTADMIN => $this->directadmin_customer_username,
            ProviderSlug::PLACEHOLDER => null,
            default => throw ResellerHostingException::noDriverFound($this->provider->slug->value),
        };
    }

    /** @return array<string,array<string>> */
    public static function namespacesWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup'],
            'App\Models\ResellerHostingSubscription' => ['subscription.product.productGroup'],
        ];
    }

    protected function casts(): array
    {
        return [
            'last_created_result_received' => 'datetime',
        ];
    }
}
