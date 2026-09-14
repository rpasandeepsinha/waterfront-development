<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\HostingDeploymentInterface;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 *  @mixin Builder<HostingDeployment>
 *
 * @property int                 $id
 * @property ?Server             $server
 * @property ?string             $plesk_customer_username
 * @property ?string             $directadmin_customer_username
 * @property ?int                $plesk_customer_id
 * @property ?int                $provider_id
 * @property ?int                $mail_only_provider_id
 * @property ?int                $mail_only_server_id
 * @property ?int                $server_id
 * @property ?Server             $mailOnlyServer
 * @property ?int                $sitebuilder_provider_id
 * @property ?Server             $basekitServer
 * @property ?int                $basekit_user_ref
 * @property ?int                $basekit_site_ref
 * @property ?int                $basekit_server_id
 * @property string              $subscription_uuid
 * @property Subscription        $subscription
 * @property ?string             $last_created_result
 * @property ?CarbonImmutable    $last_created_result_received
 * @property ?Provider           $sitebuilderProvider
 * @property ?Provider           $provider
 * @property ?Provider           $mailProvider
 * @property ?SpamExpertsCluster $spamExpertsCluster
 * @property ?int                $spam_experts_cluster_id
 * @property ?int                $wp_installation_id
 * @property ?CarbonImmutable    $created_at
 * @property ?CarbonImmutable    $updated_at
 * @property ?CarbonImmutable    $deleted_at
 * @property ?string             $ftps_host
 */
class HostingDeployment extends Model implements AuditableContract, HostingDeploymentInterface
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'hosting_deployments';

    protected $fillable = [
        'plesk_customer_username',
        'directadmin_customer_username',
        'plesk_customer_id',
        'provider_id',
        'mail_only_provider_id',
        'mail_only_server_id',
        'sitebuilder_provider_id',
        'basekit_server_id',
        'server_id',
        'subscription_uuid',
        'last_created_result',
        'last_created_result_received',
        'basekit_user_ref',
        'basekit_site_ref',
    ];

    /**
     * Defines the relation with the subscription.
     *
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
     * @return BelongsTo<Provider, $this>
     */
    public function mailProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'mail_only_provider_id', 'id');
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function sitebuilderProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'sitebuilder_provider_id', 'id');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function basekitServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'basekit_server_id', 'id');
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function mailOnlyServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'mail_only_server_id', 'id');
    }

    /**
     * @return BelongsTo<SpamExpertsCluster, $this>
     */
    public function spamExpertsCluster(): BelongsTo
    {
        return $this->belongsTo(SpamExpertsCluster::class);
    }

    public function isDefaultHostingSubscription(): bool
    {
        return $this->provider_id !== null;
    }

    /** @return array<string,array<string>> */
    public static function namespacesWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup'],
            'Modules\HostingService\Models\Subscription' => ['subscription.product.productGroup'],
        ];
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // auto-sets values on creation
        static::creating(function ($query): void {
            $query->provider_id ??= Provider::query()
                ->where('default', true)
                ->where('type', ProviderType::HOSTING)
                ->first()
                ?->id;
        });
    }

    protected function casts(): array
    {
        return [
            'basekit_user_ref' => 'int',
            'basekit_site_ref' => 'int',
            'provider_id' => 'int',
            'server_id' => 'int',
            'plesk_customer_id' => 'int',
            'subscription_uuid' => 'string',
            'last_created_result_received' => 'datetime',
        ];
    }
}
