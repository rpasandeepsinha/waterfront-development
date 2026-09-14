<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Repositories;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use UnexpectedValueException;
use Waterfront\Domain\Customers\DTO\CreateSubscriptionDTO;
use Waterfront\Domain\Customers\DTO\SubscriptionDTO;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Exceptions\CustomerAlreadyHasVolumeDiscountException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationException;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class SubscriptionRepository
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly PricePersistService $pricePersistService,
        private readonly ConfigurationInterface $configuration,
        private readonly StoreNoteAction $storeNoteAction,
    ) {
    }

    public function subscriptionExistsForCustomerIdAndDomainForType(
        int $customerId,
        string $domain,
        ProductGroupType $productGroupType,
    ): bool {
        return Subscription::query()
            ->where(
                [
                    'customer_id' => $customerId,
                    'domain' => $domain,
                ],
            )
            ->whereNotIn(
                'administrative_status',
                AdministrativeStatus::administrativelyEnded(),
            )
            ->whereProductGroupType($productGroupType)
            ->exists();
    }

    /**
     * Checks whether the product still exists for the user. A product
     * exists for the user as long as the state is not deleted.
     *
     * @throws InvalidArgumentException
     *
     * @return bool true when the product exists for the user or not
     */
    public function productExists(
        ProductGroupType $productGroupSlug,
        string $domain,
        ?int $customerId = null,
    ): bool {
        // Not checking for domains with status expired, as they should be in redemption period at the registry
        $query = Subscription::query()
            ->whereProductGroupType($productGroupSlug)
            ->where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
            ->where('domain', $domain);

        if ($customerId !== null) {
            $query = $query->where('customer_id', $customerId);
        }

        return $query->exists();
    }

    public function updateChangedTimeByProductGroup(ProductGroup $productGroup): void
    {
        foreach ($productGroup->products as $product) {
            Subscription::whereProductUuid($product->uuid)->update(['updated_at' => CarbonImmutable::now()]);
        }
    }

    public function updateChangedTimeByProduct(Product $product): void
    {
        Subscription::whereProductUuid($product->uuid)->update(['updated_at' => CarbonImmutable::now()]);
    }

    public function domainExistsInSubscription(string $domain): bool
    {
        // Not checking for domains with status expired, as they should be in redemption period at the registry
        return Subscription::where('domain', $domain)
            ->where('administrative_status', '!=', AdministrativeStatus::ARCHIVED->value)
            ->exists();
    }

    public function domainAlreadyInUse(string $domain, string $productGroupSlug): bool
    {
        return Subscription::where('domain', $domain)
            ->where('administrative_status', '<>', AdministrativeStatus::ARCHIVED->value)
            ->whereHas('product.productGroup', fn (Builder $query) => $query->where('slug', $productGroupSlug))
            ->exists();
    }

    public function createFromMigration(Customer $customer, CreateSubscriptionDTO $subscriptionDto): Subscription
    {
        $product = Product::where('slug', $subscriptionDto->slug)->with('productGroup')->first();
        assert($product instanceof Product);

        if (
            $product->productGroup->slug === ProductGroupType::VOLUME_DISCOUNT
            && $this->customerAlreadyHasVolumeDiscount($customer)
        ) {
            throw new CustomerAlreadyHasVolumeDiscountException($customer, $product);
        }

        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $prolongationPrice = $priceList->getProductPrice(
            productSlug: $subscriptionDto->slug,
            contractPeriod: $subscriptionDto->contractPeriod,
            billingPeriod: $subscriptionDto->billingPeriod,
        );

        $administrativePlaceHolderDto = new SubscriptionDTO(
            createSubscription: $subscriptionDto,
            product: $product,
            productPrice: $prolongationPrice,
        );

        $subscription = new Subscription(Arr::only(
            $administrativePlaceHolderDto->toAdministrativeArray(),
            new Subscription()->getFillable(),
        ));
        $subscription->uuid = Subscription::generateSubscriptionUuid()->toString();

        if ($subscriptionDto->fixedPrice > 0) {
            $subscription->gross_price = $subscriptionDto->fixedPrice;
            $subscription->net_price = $subscriptionDto->fixedPrice;
        }

        if ($product->productGroup->slug !== ProductGroupType::VOLUME_DISCOUNT) {
            $subscription->technical_status = $product->productGroup->slug === ProductGroupType::EXTENSION
                ? DomainStatus::ACTIVE->value
                : TechnicalStatus::OK->value;
        }

        /** @var Subscription $subscription */
        $subscription = $customer->subscriptions()->saveQuietly($subscription);

        if ($subscriptionDto->fixedPrice > 0) {
            $this->pricePersistService->persistCustomPrice(
                $subscription,
                $subscriptionDto->fixedPrice,
                $subscriptionDto->fixedPriceIsOneOff,
                CustomPriceReasonType::FIXED_MIGRATION_PRICE,
            );
        } else {
            $this->pricePersistService->persistSubscriptionPrice(
                $subscription,
                $prolongationPrice,
                CarbonImmutable::now(),
            );
        }

        if ($subscriptionDto->internalComment !== null && $subscriptionDto->internalComment !== '') {
            $this->storeNoteAction->execute($subscriptionDto->internalComment, $subscription);
        }

        if ($product->productGroup->slug === ProductGroupType::VOLUME_DISCOUNT) {
            /** @var ProductDiscount $productDiscount */
            $productDiscount = $product->productDiscount()->firstOrFail();

            if (! $productDiscount->customers()->where('customer_id', $customer->id)->exists()) {
                $productDiscount->customers()->attach($customer);
            }
        }

        return $subscription;
    }

    /**
     * Create a new subscription for the given (or current) customer.
     */
    public function create(Customer $customer, array $data): Subscription
    {
        $data['start_date'] = CarbonImmutable::now();
        $data['next_billing_date'] = CarbonImmutable::now()->addMonths($data['billing_period']);
        $data['end_date'] = CarbonImmutable::now()->addMonths($data['contract_period']);
        $data['technical_status'] = $data['status'];

        /* @var Subscription $subscription */
        $subscription = $customer
            ->subscriptions()
            ->create(
                Arr::only(
                    $data,
                    new Subscription()->getFillable(),
                ),
            );

        return $subscription;
    }

    /** @return HasMany<Subscription, Customer> */
    public function getCancelledSubscriptionsForCustomer(Customer $customer): HasMany
    {
        return $customer->hasMany(Subscription::class)->where(
            'administrative_status',
            AdministrativeStatus::CANCELED->value,
        );
    }

    public function getSubscriptionByDomainAndGroup(string $domain, ProductGroupType $productGroup): Subscription
    {
        return Subscription::query()->whereProductGroupType($productGroup)->where('domain', $domain)->firstOrFail();
    }

    public function setTechnicalStatus(Subscription $subscription, string $status): bool
    {
        $technicalStatus = TechnicalStatus::from($status);

        $subscription->technical_status = $technicalStatus->value;

        return $subscription->save();
    }

    /**
     * Get all subscriptions where the end_date is smaller than today plus the config value in days for the renewal.
     *
     * @return Collection<int, Subscription>
     */
    public function getAllDueForRenewal(): Collection
    {
        try {
            $renewalDays = $this->configuration->getAsInteger('constants.renewal-days');
        } catch (ConfigurationException $exception) {
            throw new UnexpectedValueException('RENEWAL_DAYS is not set in the env!', previous: $exception);
        }

        $renewalDate = CarbonImmutable::today()->addDays($renewalDays);

        return Subscription::query()
            ->where('end_date', '<', $renewalDate)
            ->whereIn('administrative_status', [
                AdministrativeStatus::ACTIVE->value,
                AdministrativeStatus::SUSPENDED->value,
            ])
            ->whereNull('parent_subscription_id')
            ->get();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getAllCustomersEligibleForInvoicing(DateTimeInterface $billingDate): Collection
    {
        $customerTableName = new Customer()->getTable();

        return Customer::whereIn(
            'id',
            function ($query) use ($customerTableName, $billingDate) {
                $query
                    ->select('customers.id')
                    ->from($customerTableName . ' AS customers')
                    ->join('subscriptions', 'customers.id', '=', 'subscriptions.customer_id')
                    ->where('subscriptions.next_billing_date', '<', $billingDate)
                    ->whereIn('subscriptions.administrative_status', [
                        AdministrativeStatus::ACTIVE->value,
                        AdministrativeStatus::CANCELED->value,
                        AdministrativeStatus::SUSPENDED->value,
                    ])
                    ->whereNull('subscriptions.parent_subscription_id')
                    ->groupBy(['customers.id']);
            },
        )->get();
    }

    /**
     * @return LazyCollection<int, Subscription>
     */
    public function getAllDueForInvoicing(Customer $customer, DateTimeInterface $billingDate): LazyCollection
    {
        return Subscription::where('next_billing_date', '<', $billingDate)
            ->whereIn(
                'administrative_status',
                [
                    AdministrativeStatus::ACTIVE->value,
                    AdministrativeStatus::CANCELED->value,
                    AdministrativeStatus::SUSPENDED->value,
                ],
            )
            ->where('customer_id', $customer->id)
            ->whereNull('parent_subscription_id')
            ->with(
                'customer',
                'product',
                'product.productGroup',
                'children',
                'children.customer',
                'children.product',
                'children.product.productGroup',
            )
            ->cursor();
    }

    public function countDueForInvoicing(DateTimeInterface $billingDate): int
    {
        return Subscription::where('next_billing_date', '<', $billingDate)
            ->whereIn(
                'administrative_status',
                [
                    AdministrativeStatus::ACTIVE->value,
                    AdministrativeStatus::CANCELED->value,
                    AdministrativeStatus::SUSPENDED->value,
                ],
            )
            ->count();
    }

    /**
     * Get parent subscriptions where administratively deleted/expired and technically suspended or
     * gets the parent subscriptions where children are administratively deleted/expired and technically suspended.
     *
     * @return Collection<int, Subscription>
     */
    public function getAllDueForTermination(): Collection
    {
        $today = CarbonImmutable::now()->format(DateTimeFormat::DATE);

        return Subscription::query()
            ->whereNull('parent_subscription_id')
            ->whereHas('children', function (Builder $query) use ($today) {
                // Future proofing for suspended child subscriptions
                $query->where('termination_date', '<=', $today)->where(
                    'administrative_status',
                    AdministrativeStatus::EXPIRED->value,
                );
            })
            ->orWhere(function (Builder $query) use ($today) {
                $query
                    ->where('termination_date', '<=', $today)
                    ->whereNull('parent_subscription_id')
                    ->where(function (Builder $query) {
                        $query->where('administrative_status', AdministrativeStatus::EXPIRED->value);
                    });
            })
            ->with('children')
            ->get();
    }

    /**
     * Get all canceled domain subscriptions that are about to expire. These are used for disabling the autorenewal.
     *
     * @return Collection<int, Subscription>
     */
    public function getDomainSubscriptionsDueForAutoRenewalDisable(): Collection
    {
        return Subscription::whereHas(
            'product.productGroup',
            function (Builder $productGroupQuery): void {
                $productGroupQuery->where('slug', ProductGroupType::EXTENSION);
            },
        )
            ->where('end_date', '<=', CarbonImmutable::today()->addDays(3))
            ->where('administrative_status', AdministrativeStatus::CANCELED->value)
            ->where('technical_status', DomainStatus::ACTIVE->value)
            ->with('domainDeployment')
            ->get();
    }

    /**
     * @return string[]
     */
    public function getRecentDomainNames(CarbonImmutable $createdAfter, int $limit): array
    {
        /** @var string[] $domains */
        $domains = Subscription::whereHas(
            'product.productGroup',
            function (Builder $productGroupQuery): void {
                $productGroupQuery->where('slug', ProductGroupType::EXTENSION);
            },
        )
            ->whereNotNull('domain')
            ->where('created_at', '>=', $createdAfter)
            ->limit($limit)
            ->pluck('domain')
            ->all();

        return $domains;
    }

    /**
     * Get all manual subscriptions that have ended but have not yet been handled properly.
     *
     * @return Collection<int, Subscription>
     */
    public function getNotTerminatedManualSubscriptions(): Collection
    {
        return Subscription::query()
            ->whereProductGroupType(ProductGroupType::MANUAL_SUBSCRIPTION)
            ->where('administrative_status', AdministrativeStatus::ARCHIVED->value)
            ->where('technical_status', DomainStatus::ACTIVE->value)
            ->get();
    }

    /**
     * Get subscriptions that are dependent on a given subscription. These dependent subscription
     * should be "in sync" with the given subscription and connect exist without the given one.
     * For example; child subscriptions or a freeDns subscription that's dependent to a domain subscription.
     *
     * @return SupportCollection<int, Subscription>
     */
    public function getDependentSubscriptions(Subscription $subscription): SupportCollection
    {
        $dependentSubscriptions = new SupportCollection();

        foreach ($subscription->children as $child) {
            $dependentSubscriptions->add($child);
        }

        $dependentSubscriptions->add(
            $this->getDependentSubscriptionByProductGroup(
                $subscription,
                ProductGroupType::HOSTING,
                ProductType::getRedirectProductTypes(),
            ),
        );

        $dependentSubscriptions->add(
            $this->getDependentSubscriptionByProductGroup(
                $subscription,
                ProductGroupType::DNS,
                ProductType::getDnsProductTypes(),
            ),
        );

        return $dependentSubscriptions->filter()->unique(fn (Subscription $subscription): int => $subscription->id);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findHostingDeploymentBySubscription(Subscription $subscription): HostingDeployment
    {
        return $subscription->hostingDeployment()->firstOrFail();
    }

    public function getSubscriptionByHostingDeployment(HostingDeployment $hostingDeployment): Subscription
    {
        return $hostingDeployment->subscription;
    }

    public function getNotAdministrativelyEndedOrSuspendedDnsSubscription(string $domain): Subscription
    {
        /** @var Subscription $dnsSubscription */
        $dnsSubscription = Subscription::whereProductGroupType(ProductGroupType::DNS)
            ->where('domain', $domain)
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::SUSPENDED->value,
            ])
            ->firstOrFail();

        return $dnsSubscription;
    }

    /**
     * Get all subscriptions that have been canceled and passed the end date.
     *
     * @return Collection<int, Subscription>
     */
    public function getAllExpiringSubscriptions(CarbonImmutable $endDate): Collection
    {
        $sub = Subscription::query()
            ->select('parent.id')
            ->from('subscriptions', 'parent')
            ->leftJoin('subscriptions as children', function (JoinClause $join) use ($endDate) {
                $join->on('children.parent_subscription_id', '=', 'parent.id')->where(
                    'children.administrative_status',
                    '=',
                    AdministrativeStatus::CANCELED->value,
                )->where('children.end_date', '<', $endDate->format(DateTimeFormat::DATE));
            })
            ->where(function (Builder $query) use ($endDate) {
                $query
                    ->where('parent.administrative_status', '=', AdministrativeStatus::CANCELED->value)
                    ->where('parent.end_date', '<', $endDate->format(DateTimeFormat::DATE))
                    ->whereNull('parent.parent_subscription_id');
            })
            ->orWhereNotNull('children.id')
            ->groupBy('parent.id');

        $query = Subscription::joinSub($sub, 'sub', function (JoinClause $join) {
            $join->on('sub.id', '=', 'subscriptions.id');
        });

        return $query->get();
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getActiveDnsSubscription(string $domain): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = Subscription::whereProductGroupType(ProductGroupType::DNS)
            ->whereNotIn(
                'administrative_status',
                AdministrativeStatus::administrativelyEnded(),
            )
            ->where('domain', $domain)
            ->firstOrFail();

        return $subscription;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getDnsSubscriptionsNotUsingProduct(Customer $customer, Product $product): Collection
    {
        return Subscription::query()
            ->where('customer_id', $customer->id)
            ->whereProductGroupType(ProductGroupType::DNS)
            ->whereNot('product_uuid', $product->uuid)
            ->whereNotIn('administrative_status', [
                ...AdministrativeStatus::administrativelyEnded(),
                AdministrativeStatus::SUSPENDED->value,
            ])
            ->with('product')
            ->get();
    }

    public function getByUuid(UuidInterface|string $uuid): ?Subscription
    {
        if ($uuid instanceof UuidInterface) {
            $uuid = $uuid->toString();
        }

        return Subscription::where(['uuid' => $uuid])->first();
    }

    public function findById(int $id): ?Subscription
    {
        return Subscription::query()->where('id', $id)->first();
    }

    public function getById(int $id): Subscription
    {
        return Subscription::query()->where('id', $id)->firstOrFail();
    }

    /**
     * @param list<string> $uuids
     *
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionsByUuid(array $uuids): Collection
    {
        return Subscription::whereIn('uuid', $uuids)->with('product')->get();
    }

    public function getSubscriptionByCustomerDomainAndType(
        Customer $customer,
        string $domain,
        ProductGroupType $productGroupType,
    ): ?Subscription {
        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('domain', $domain)
            ->whereProductGroupType($productGroupType)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->first();

        return $subscription;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function findByDomainAndType(
        string $domain,
        ProductGroupType $productGroupType,
    ): Subscription {
        return Subscription::query()
            ->where('domain', $domain)
            ->whereProductGroupType($productGroupType)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->firstOrFail();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function findByMigratedSubscriptionReference(string $reference): Collection
    {
        return Subscription::query()
            ->whereHas(
                'migratedSubscriptions',
                static fn (Builder $builder) => $builder->where('reference_subscription_id', 'ilike', "%$reference%"),
            )
            ->with(['customer', 'migratedSubscriptions'])
            ->get();
    }

    public function findByDomain(
        string $domain,
    ): ?Subscription {
        return Subscription::query()
            ->where('domain', $domain)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->first();
    }

    public function isExpired(Subscription $subscription): bool
    {
        if (
            $subscription->administrative_status === AdministrativeStatus::CANCELED->value
            && $subscription->end_date <= CarbonImmutable::now()
        ) {
            return true;
        }

        return (
            $subscription->administrative_status === AdministrativeStatus::EXPIRED->value
            && $subscription->end_date <= CarbonImmutable::now()
            && $subscription->termination_date < CarbonImmutable::now()
        );
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getActiveSubscriptionOverview(Customer $customer): Collection
    {
        return Subscription::query()
            ->where('customer_id', $customer->id)
            ->with([
                'product.productGroup',
                'customer',
                'transfers',
                'product.productSpecs',
                'hostingDeployment',
                'orderLineItem',
                'labels',
            ])
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->where(function (Builder $subscriptionQuery): void {
                $subscriptionQuery
                    ->where(function (Builder $nullGroupQuery): void {
                        $nullGroupQuery->whereNotNull('domain')->where('domain', '!=', '');
                    })
                    // see https://yh-jira.atlassian.net/browse/WATER-4321
                    ->orWhereHas('product.productGroup', function (Builder $productGroupQuery): void {
                        $productGroupQuery->whereIn('slug', [
                            ProductGroupType::MANUAL_SUBSCRIPTION,
                            ProductGroupType::ADD_ON,
                            ProductGroupType::OTHER,
                            ProductGroupType::VPS,
                            ProductGroupType::VOLUME_DISCOUNT,
                        ]);
                    });
            })
            ->get();
    }

    public function isDowngradeAlreadyRequested(Subscription $subscription): bool
    {
        return SubscriptionChange::query()
            ->where('subscription_uuid', $subscription->uuid)
            ->whereIn('status', [
                SubscriptionChangeStatus::REQUESTED->value,
                SubscriptionChangeStatus::INPROGRESS->value,
            ])
            ->where('type', ProductChangeType::DOWNGRADE)
            ->whereNull('completed_at')
            ->exists();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getAllActiveParentSubscriptions(Customer $customer): Collection
    {
        return Subscription::query()
            ->where('customer_id', '=', $customer->id)
            ->whereNull('parent_subscription_id')
            ->whereNotIn('administrative_status', [
                AdministrativeStatus::ARCHIVING->value,
                ...AdministrativeStatus::administrativelyEnded(),
            ])
            ->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getAllSubscriptionsForCustomerBasedOnGroupFilter(
        Customer $customer,
        ?ProductGroupType $filter,
    ): Collection {
        return Subscription::query()
            ->where('customer_id', $customer->id)
            ->where(function (SubscriptionQueryBuilder $builder) use ($filter): void {
                if ($filter === null) {
                    return;
                }

                match ($filter) {
                    ProductGroupType::OTHER => $builder->whereProductGroupTypes([
                        ProductGroupType::MANUAL_SUBSCRIPTION,
                        ProductGroupType::OTHER,
                        ProductGroupType::VOLUME_DISCOUNT,
                        ProductGroupType::ADD_ON,
                    ]),
                    default => $builder->whereProductGroupType($filter),
                };
            })
            ->where(function (Builder $builder) use ($filter): void {
                if (
                    $filter !== null
                    && ! in_array($filter, [ProductGroupType::OTHER, ProductGroupType::BACKUP], true)
                ) {
                    $builder->whereNotNull('domain')->where('domain', '!=', '');
                }
            })
            ->whereHas('product', function (Builder $query) {
                $query->whereNotLike('slug', 'domain_trustee_%');
            })
            ->with([
                'product.productGroup',
                'labels',
                'domainDeployment',
                'parent',
                'transfers',
            ])
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->get();
    }

    /**
     * @return SupportCollection<int, Subscription>
     */
    public function getActiveFreeRedirectSubscriptions(int $limit): SupportCollection
    {
        return Subscription::query()
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->whereHas('product', function (Builder $query): void {
                $query->where('slug', ProductType::FREE_REDIRECT);
            })
            ->take($limit)
            ->get();
    }

    public function customerAlreadyHasVolumeDiscount(Customer $customer): bool
    {
        $coupledToVolumeDiscount = $customer
            ->subscriptions()
            ->whereHas('product.productGroup', function ($q) {
                $q->where('slug', ProductGroupType::VOLUME_DISCOUNT);
            })
            ->exists();

        $customerGenericDiscount = $customer->productDiscounts()->exists();

        return $coupledToVolumeDiscount || $customerGenericDiscount;
    }

    public function getCustomerByUuid(string $subscriptionUuid): ?Customer
    {
        return Subscription::query()->where('uuid', $subscriptionUuid)->first()?->customer;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function findAllByCustomerId(int $customerId): Collection
    {
        return Subscription::query()->where('customer_id', $customerId)->get();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function findAllSubscriptionsInHostingGroupWithoutServicePlusProductSpec(int $customerId): Collection
    {
        return Subscription::query()
            ->where('customer_id', $customerId)
            ->whereNull('parent_subscription_id')
            ->whereHas(
                'product',
                fn (Builder $productQuery) => $productQuery
                    ->whereHas(
                        'productGroup',
                        fn (Builder $groupQuery) => $groupQuery->where('slug', ProductGroupType::HOSTING),
                    )
                    ->where('orderable', true)
                    ->whereDoesntHave(
                        'productSpecs',
                        fn (Builder $specQuery) => $specQuery->where(
                            'name',
                            ProductSpecName::HAS_SERVICE_PLUS->value,
                        )->whereIn('value', [true, 'true', '1', 1, 'yes']),
                    ),
            )
            ->whereDoesntHave(
                'children',
                fn (Builder $childQuery) => $childQuery->whereHas(
                    'product',
                    fn (Builder $productQuery) => $productQuery->whereHas(
                        'productGroup',
                        fn (Builder $groupQuery) => $groupQuery->where('slug', ProductGroupType::ADD_ON),
                    )->whereHas(
                        'productSpecs',
                        fn (Builder $specQuery) => $specQuery->where(
                            'name',
                            ProductSpecName::HAS_SERVICE_PLUS->value,
                        )->whereIn('value', [true, 'true', '1', 1, 'yes']),
                    ),
                )->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded()),
            )
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->get();
    }

    public function getSubscriptionsWhereProductSlugAndCustomerMatchCount(
        Customer $customer,
        string $productSlug,
    ): int {
        return Subscription::query()
            ->where('customer_id', $customer->id)
            ->whereProductGroupType(ProductGroupType::MICROSOFT_365)
            ->whereProductSlug($productSlug)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->count();
    }

    public function getSubscriptionsWhereProductSlugAndCustomerDoesNotMatchCount(
        Customer $customer,
        string $productSlug,
    ): int {
        return Subscription::query()
            ->where('customer_id', $customer->id)
            ->whereProductGroupType(ProductGroupType::MICROSOFT_365)
            ->whereProductSlugIsNot($productSlug)
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->count();
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getFailedDomainSubscriptionsWithDeployment(): Collection
    {
        return $this->getByTechnicalStatus([
            TechnicalStatus::FAILED->value,
            TechnicalStatus::FAI->value,
        ])
            ->whereHas('domainDeployment')
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->with('domainDeployment')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param array<ProductType> $productTypes
     */
    private function getDependentSubscriptionByProductGroup(
        Subscription $subscription,
        ProductGroupType $productGroupType,
        array $productTypes,
    ): ?Subscription {
        if (! $subscription->product->isDomainProduct()) {
            return null;
        }

        return Subscription::whereProductGroupType($productGroupType)
            ->whereProductSlugs($productTypes)
            ->where('customer_id', $subscription->customer_id)
            ->where('domain', $subscription->domain)
            ->whereNotIn('administrative_status', [
                AdministrativeStatus::ARCHIVED->value,
                AdministrativeStatus::ARCHIVING->value,
            ])
            ->first();
    }

    /**
     * @param string[] $technicalStatus
     *
     * @return Builder<Subscription>
     */
    private function getByTechnicalStatus(array $technicalStatus): Builder
    {
        return Subscription::query()->whereIn('technical_status', $technicalStatus);
    }
}
