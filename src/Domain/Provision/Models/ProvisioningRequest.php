<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Database\UuidCast;

/**
 * @property int                                   $id
 * @property ?int                                  $retry_of_request_id
 * @property UuidInterface                         $uuid
 * @property UuidInterface                         $tag
 * @property ?UuidInterface                        $context_uuid
 * @property ?UuidInterface                        $requested_by_uuid
 * @property string                                $request_data
 * @property ProvisionRequestName                  $request_name
 * @property ProvisionType                         $request_type
 * @property ?ProvisionProvider                    $provision_provider
 * @property ?CarbonImmutable                      $created_at
 * @property ?CarbonImmutable                      $updated_at
 * @property ?ProvisioningResult                   $result
 * @property ?Subscription                         $subscription
 * @property ?ProvisionDeployment                  $deployment
 * @property ?ProvisioningRequest                  $retryOf
 * @property ?Collection<int, ProvisioningRequest> $retryRequests
 *
 * @mixin Builder<ProvisioningRequest>
 */
class ProvisioningRequest extends Model
{
    protected $table = 'provisioning_requests';

    /** @return HasOne<ProvisioningResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(ProvisioningResult::class, 'request_id');
    }

    /**
     * @return HasOne<RedirectDeployment, $this>|HasOne<HostingDeployment, $this>|HasOne<SitebuilderDeployment, $this>|HasOne<BackupDeployment, $this>|null
     */
    public function deployment(): ?HasOne
    {
        $type = $this->request_type;

        return match ($type) {
            ProvisionType::REDIRECT =>  $this->hasOne(RedirectDeployment::class, 'origin_provisioning_request_id'),
            ProvisionType::HOSTING =>  $this->hasOne(HostingDeployment::class, 'origin_provisioning_request_id'),
            ProvisionType::SITEBUILDER =>  $this->hasOne(SitebuilderDeployment::class, 'origin_provisioning_request_id'),
            ProvisionType::BACKUP =>  $this->hasOne(BackupDeployment::class, 'origin_provisioning_request_id'),
            default => null,
        };
    }

    /**
     * @return HasMany<ProvisioningRequest, $this>|null
     */
    public function retryRequests(): ?HasMany
    {
        return $this->hasMany(related: ProvisioningRequest::class, foreignKey: 'retry_of_request_id', localKey: 'id');
    }

    /**
     * @return BelongsTo<ProvisioningRequest, $this>|null
     */
    public function retryOf(): ?BelongsTo
    {
        return $this->belongsTo(related: ProvisioningRequest::class, foreignKey: 'retry_of_request_id', ownerKey: 'id');
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'uuid', 'tag');
    }

    protected function casts(): array
    {
        return [
            'tag' => UuidCast::class,
            'uuid' => UuidCast::class,
            'context_uuid' => UuidCast::class,
            'requested_by_uuid' => UuidCast::class,
            'request_name' => ProvisionRequestName::class,
            'request_type' => ProvisionType::class,
            'provision_provider' => ProvisionProvider::class,
        ];
    }
}
