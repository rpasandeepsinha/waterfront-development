<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection as BaseCollection;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365HttpLog;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\Hosting\Models\HostingDeployment as ProvisionHostingDeployment;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\Ssl\Models\Sanity as SslSanity;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;

/**
 * @property int                                     $id
 * @property string                                  $uuid
 * @property string                                  $product_uuid
 * @property Customer                                $customer
 * @property ?OrderLineItem                          $orderLineItem
 * @property ?DomainDeployment                       $domainDeployment
 * @property ?HostingDeployment                      $hostingDeployment
 * @property ?VirtualMachineDeployment               $cloudStackVirtualMachineDeployment
 * @property ?VolumeDeployment                       $cloudStackVolumeDeployment
 * @property ?SslDeployment                          $sslDeployment
 * @property ?ResellerHostingDeployment              $resellerHostingDeployment
 * @property ?SslSanity                              $sslSanity
 * @property int                                     $customer_id
 * @property Product                                 $product
 * @property ?string                                 $domain
 * @property ?string                                 $technical_status
 * @property string                                  $administrative_status
 * @property int                                     $billing_period
 * @property int                                     $contract_period
 * @property int<0, max>                             $gross_price
 * @property int<0, max>                             $net_price
 * @property CarbonImmutable                         $start_date
 * @property ?CarbonImmutable                        $cancel_date
 * @property ?CarbonImmutable                        $termination_date
 * @property ?SubscriptionCancelReason               $cancel_reason
 * @property ?int                                    $parent_subscription_id
 * @property ?Subscription                           $parent
 * @property CarbonImmutable                         $next_billing_date
 * @property CarbonImmutable                         $end_date
 * @property ?CarbonImmutable                        $created_at
 * @property ?CarbonImmutable                        $updated_at
 * @property ?CarbonImmutable                        $suspended_at
 * @property Collection<int, Subscription>           $children
 * @property Collection<int, Transfer>               $transfers
 * @property Collection<int, Invoice>                $invoices
 * @property Collection<int, SubscriptionMutation>   $mutations
 * @property Pivot                                   $pivot
 * @property Collection<int, MigratedSubscription>   $migratedSubscriptions
 * @property Collection<int, Label>                  $labels
 * @property ?SitebuilderDeployment                  $provisionSitebuilderDeployment
 * @property ?Microsoft365Deployment                 $microsoft365Deployment
 * @property ?DnsDeployment                          $dnsDeployment
 * @property ?SubscriptionCategories                 $category
 * @property Collection<int, CancellationFlow>       $cancellationFlows
 * @property int                                     $subscription_price_id
 * @property ?SubscriptionPrice                      $activePrice
 * @property Collection<int, SubscriptionPrice>      $prices
 * @property Collection<int, CustomerRetentionOffer> $retentionOffers
 * @property Collection<int, Experiment>             $experiments
 * @property ?BackupDeployment                       $provisionBackupDeployment
 *
 * @method static SubscriptionQueryBuilder query()
 * @method static SubscriptionQueryBuilder whereCustomerId($value)
 * @method static SubscriptionQueryBuilder whereAdministrativeStatus($value)
 * @method static SubscriptionQueryBuilder whereProductName($name)
 * @method static SubscriptionQueryBuilder whereProductSlug($slug)
 * @method static SubscriptionQueryBuilder whereProductGroupType($productGroupTypeSlug)
 * @method static SubscriptionQueryBuilder whereProductUuid($productUuid)
 *
 * @mixin SubscriptionQueryBuilder
 * @mixin Builder<Subscription>
 */
class Subscription extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'subscriptions';

    protected $fillable = [
        'uuid',
        'product_uuid',
        'customer_id',
        'domain',
        'technical_status',
        'administrative_status',
        'billing_period',
        'contract_period',
        'gross_price',
        'net_price',
        'start_date',
        'cancel_date',
        'next_billing_date',
        'end_date',
        'parent_subscription_id',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            $model->uuid ??= self::generateSubscriptionUuid()->toString();
        });
    }

    public static function generateSubscriptionUuid(): UuidInterface
    {
        return Uuid::uuid4();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid')->withTrashed();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_id', 'id');
    }

    /**
     * @return HasMany<SubscriptionMutation, $this>
     */
    public function mutations(): HasMany
    {
        return $this->hasMany(SubscriptionMutation::class, 'subscription_id', 'id');
    }

    /**
     * @return BelongsToMany<Label, $this>
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    /**
     * @return BelongsToMany<MigratedSubscription, $this>
     */
    public function migratedSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(MigratedSubscription::class);
    }

    /**
     * @return BelongsToMany<Experiment, $this>
     */
    public function experiments(): BelongsToMany
    {
        return $this->belongsToMany(Experiment::class, 'experiment_subscriptions', 'subscription_id', 'experiment_id');
    }

    /**
     * @return HasOne<OrderLineItem, $this>
     */
    public function orderLineItem(): HasOne
    {
        return $this->hasOne(OrderLineItem::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<DomainDeployment, $this>
     */
    public function domainDeployment(): HasOne
    {
        return $this->hasOne(DomainDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<HostingDeployment, $this>
     */
    public function hostingDeployment(): HasOne
    {
        return $this->hasOne(HostingDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<DnsDeployment, $this>
     */
    public function dnsDeployment(): HasOne
    {
        return $this->hasOne(DnsDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasMany<DnsRecordChange, $this>
     */
    public function dnsLogs(): HasMany
    {
        return $this->hasMany(DnsRecordChange::class);
    }

    /**
     * @return HasOne<VirtualMachineDeployment, $this>
     */
    public function cloudStackVirtualMachineDeployment(): HasOne
    {
        return $this->hasOne(VirtualMachineDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<VolumeDeployment, $this>
     */
    public function cloudStackVolumeDeployment(): HasOne
    {
        return $this->hasOne(VolumeDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOneThrough<RedirectDeployment, ProvisioningRequest, $this>
     */
    public function redirectDeployment(): HasOneThrough
    {
        return $this->hasOneThrough(
            RedirectDeployment::class,
            ProvisioningRequest::class,
            firstKey: 'tag',
            secondKey: 'origin_provisioning_request_id',
            localKey: 'uuid',
            secondLocalKey: 'id',
        );
    }

    /**
     * @return HasOneThrough<ProvisionHostingDeployment, ProvisioningRequest, $this>
     */
    public function provisionHostingDeployment(): HasOneThrough
    {
        return $this->hasOneThrough(
            ProvisionHostingDeployment::class,
            ProvisioningRequest::class,
            firstKey: 'tag',
            secondKey: 'origin_provisioning_request_id',
            localKey: 'uuid',
            secondLocalKey: 'id',
        );
    }

    /**
     * @return HasOneThrough<SitebuilderDeployment, ProvisioningRequest, $this>
     */
    public function provisionSitebuilderDeployment(): HasOneThrough
    {
        return $this->hasOneThrough(
            SitebuilderDeployment::class,
            ProvisioningRequest::class,
            firstKey: 'tag',
            secondKey: 'origin_provisioning_request_id',
            localKey: 'uuid',
            secondLocalKey: 'id',
        );
    }

    /**
     * @return HasOne<SslDeployment, $this>
     */
    public function sslDeployment(): HasOne
    {
        return $this->hasOne(SslDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<SslSanity, $this>
     */
    public function sslSanity(): HasOne
    {
        return $this->hasOne(SslSanity::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasOne<ResellerHostingDeployment, $this>
     */
    public function resellerHostingDeployment(): HasOne
    {
        return $this->hasOne(ResellerHostingDeployment::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Subscription::class, 'parent_subscription_id', 'id');
    }

    /**
     * @return HasMany<SubscriptionChange, $this>
     */
    public function subscriptionChanges(): HasMany
    {
        return $this->hasMany(SubscriptionChange::class, 'subscription_uuid', 'uuid');
    }

    /**
     * @return HasMany<OneTimeService, $this>
     */
    public function oneTimeServices(): HasMany
    {
        return $this->hasMany(OneTimeService::class, 'subscription_id', 'id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'parent_subscription_id', 'id');
    }

    /**
     * @return HasOne<Notes, $this>
     */
    public function notes(): HasOne
    {
        return $this->hasOne(Notes::class, 'subscription_id', 'id');
    }

    /**
     * @return HasOne<SubscriptionCategories, $this>
     */
    public function category(): HasOne
    {
        return $this->hasOne(SubscriptionCategories::class, 'subscription_id', 'id');
    }

    /**
     * @return BelongsToMany<Transfer, $this>
     */
    public function transfers(): BelongsToMany
    {
        return $this->belongsToMany(
            Transfer::class,
        )->withPivot(['executed_at', 'failed_at']);
    }

    /**
     * @return HasOne<Microsoft365Deployment, $this>
     */
    public function microsoft365Deployment(): HasOne
    {
        if ($this->parent_subscription_id === null) {
            return $this->hasOne(Microsoft365Deployment::class, 'subscription_id', 'id');
        }

        return $this->hasOne(Microsoft365Deployment::class, 'subscription_id', 'parent_subscription_id');
    }

    /**
     * @return HasOneThrough<Microsoft365CustomerInfo, Microsoft365Deployment, $this>
     */
    public function microsoft365Customer(): HasOneThrough
    {
        if ($this->parent_subscription_id === null) {
            return $this->hasOneThrough(
                Microsoft365CustomerInfo::class,
                Microsoft365Deployment::class,
                'subscription_id',
                'id',
                'id',
                'microsoft365_customer_info_id',
            );
        }

        return $this->hasOneThrough(
            Microsoft365CustomerInfo::class,
            Microsoft365Deployment::class,
            'subscription_id',
            'id',
            'parent_subscription_id',
            'microsoft365_customer_info_id',
        );
    }

    /**
     * @return HasMany<Microsoft365HttpLog, $this>
     */
    public function microsoft365Logs(): HasMany
    {
        return $this->hasMany(Microsoft365HttpLog::class);
    }

    /**
     * @return BaseCollection<int, HostingDeployment|DomainDeployment|SslDeployment|ResellerHostingDeployment>
     */
    public function getDeployments(): BaseCollection
    {
        return new BaseCollection([
            $this->hostingDeployment,
            $this->domainDeployment,
            $this->sslDeployment,
            $this->resellerHostingDeployment,
        ])->filter();
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['product.productGroup'],
        ];
    }

    public function newEloquentBuilder($query): SubscriptionQueryBuilder
    {
        return new SubscriptionQueryBuilder($query);
    }

    /**
     * @return HasOneThrough<BackupDeployment, ProvisioningRequest, $this>
     */
    public function provisionBackupDeployment(): HasOneThrough
    {
        return $this->hasOneThrough(
            BackupDeployment::class,
            ProvisioningRequest::class,
            firstKey: 'tag',
            secondKey: 'origin_provisioning_request_id',
            localKey: 'uuid',
            secondLocalKey: 'id',
        );
    }

    /**
     * @return HasOne<SubscriptionPrice, $this>
     */
    public function activePrice(): HasOne
    {
        return $this->hasOne(SubscriptionPrice::class, 'id', 'subscription_price_id');
    }

    /**
     * @return HasMany<SubscriptionPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPrice::class);
    }

    /**
     * @return HasMany<CustomerRetentionOffer, $this>
     */
    public function retentionOffers(): HasMany
    {
        return $this->hasMany(CustomerRetentionOffer::class, 'subscription_id', 'id');
    }

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'customer_id' => 'int',
            'billing_period' => 'int',
            'contract_period' => 'int',
            'net_price' => 'int',
            'gross_price' => 'int',
            'parent_subscription_id' => 'int',
            'start_date' => 'datetime',
            'cancel_date' => 'datetime',
            'cancel_reason' => SubscriptionCancelReason::class,
            'termination_date' => 'datetime',
            'next_billing_date' => 'datetime',
            'end_date' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
