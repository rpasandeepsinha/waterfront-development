<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Experiment\Repositories\ExperimentRepository;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Orders\Jobs\ProcessOrderJob;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Actions\MailSubscriptionCreatedAction;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionUpdateRequestDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Helpers\Money;
use Webmozart\Assert\Assert;

class SubscriptionService
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly MailerInterface $mailer,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly MailSubscriptionCreatedAction $mailSubscriptionCreatedAction,
        private readonly LoggerInterface $logger,
        private readonly JobDispatcher $jobDispatcher,
        private readonly ProvisionService $provisionService,
        private readonly PriceResolver $priceResolver,
        private readonly PricePersistService $pricePersistService,
        private readonly ExperimentRepository $experimentRepository,
    ) {
    }

    public function dispatchProcessOrderJob(Order $order): void
    {
        $this->jobDispatcher->dispatch(new ProcessOrderJob($order));
    }

    public function createSubscriptionsFromOrder(Order $order): void
    {
        $this->logger->info(
            sprintf('Creating base subscriptions from order %d', $order->id),
            [
                LoggingContextKeys::ORDER_ID => $order->id,
            ],
        );

        $order->loadMissing(
            'lineItems.product.productGroup',
            'lineItems.parentSubscription',
            'lineItems.product.productSpecs',
            'lineItems.subscription',
            'lineItems.voucherClaim.voucher',
        );

        $orderLineItems = [];

        //Filtering all unprocessed
        $unProcessedOrderLineItems = $order->lineItems->filter(
            fn (OrderLineItem $item) => $item->processed_at === null,
        );

        /*
         * @see https://yh-jira.atlassian.net/browse/SWD-379
         * For some weird reason the line items are not always sorted the same way.
         * In the end we should first handle all parent subscriptions and then the
         * children. Unfortunately that is not how the current facade-collection setup
         * is implemented, so we apply a (short term, not waterproof) solution to sort
         * the order line items on parent id ascending. Sort of making sure the parents
         * are handled first.
         */
        foreach ($unProcessedOrderLineItems->sortBy('parent_id') as $orderLineItem) {
            if ($orderLineItem->subscription instanceof Subscription) {
                continue;
            }

            if ($orderLineItem->product?->productGroup->slug === ProductGroupType::ONE_TIME_SERVICE) {
                continue;
            }

            $orderLineItem->subscription_uuid = Str::uuid()->toString();
            $orderLineItem->save();

            $type = $orderLineItem->product?->productGroup->slug->value;
            $orderLineItems[$type][] = $orderLineItem;
        }

        if (count($orderLineItems) === 0) {
            $this->logger->debug('No subscriptions to process');

            return;
        }

        /* @see https://yh-jira.atlassian.net/browse/SWD-4213
         * Ordering of types in specific order is also required because the original
         * subscription create facade setup was not build with parent child relations
         * in mind.
         * This causes problems specifically if the parent and child are not within
         * the same product group. For example free-DNS is 'hosting', while the
         * domain is 'extension'. Now if the hosting type is being processed first,
         * the parent of the free-DNS will not be processed yet.
         * So a duck-tape fix here is to make sure extensions are processed before
         * hosting or anything else.
         */
        $typesOrder = ['extension' => 1, 'hosting' => 2];
        /** @var array<string, list<OrderLineItem>> $sortedOrderLineItems */
        $sortedOrderLineItems = new Collection($orderLineItems)
            ->sortKeysUsing(
                fn ($typeKeyA, $typeKeyB) => ($typesOrder[$typeKeyA] ?? 999) <=> ($typesOrder[$typeKeyB] ?? 999),
            )
            ->toArray();

        $subscriptionGroups = [];

        foreach ($sortedOrderLineItems as $productGroupType => $orderGroup) {
            foreach ($orderGroup as $orderLineItem) {
                $subscription = $this->createSubscription($orderLineItem, $order->customer);

                $subscriptionGroups[$productGroupType][] = $subscription;
                $orderLineItem->processed_at = CarbonImmutable::now();
                $orderLineItem->save();
            }
        }

        foreach ($subscriptionGroups as $subscriptionGroup) {
            $this->provisionService->provision($subscriptionGroup);
        }

        $this->mailSubscriptionCreatedAction->execute($order->customer, [
            'customer_id' => $order->customer->id,
            'subscriptions' => $order->lineItems->reduce(
                function (array $subscriptions, OrderLineItem $orderLineItem) {
                    $product = $orderLineItem->product;
                    assert($product instanceof Product);
                    $slug = $product->productGroup->slug->value;

                    if (! array_key_exists($slug, $subscriptions)) {
                        $subscriptions[$slug] = [];
                    }

                    $amountClaimed = $orderLineItem->voucherClaim->amount_claimed ?? null;
                    if ($amountClaimed !== null) {
                        $amountClaimed = Money::format($amountClaimed);
                    }

                    $subscriptions[$slug][] = [
                        'name' => $orderLineItem->product_name,
                        'domain' => $orderLineItem->domain,
                        'price' => $orderLineItem->net_price,
                        'contract_period' => $orderLineItem->contract_period,
                        'billing_period' => $orderLineItem->billing_period,
                        'voucher_code' => $orderLineItem->voucherClaim->voucher->code ?? null,
                        'amount_claimed' => $amountClaimed,
                    ];

                    return $subscriptions;
                },
                [],
            ),
        ]);
    }

    public function createSubscriptionFromOrderLineItem(
        OrderLineItem $orderLineItem,
        bool $manageSubscription,
    ): Subscription {
        $customer = $orderLineItem->order->customer;

        $subscription = $this->createSubscription($orderLineItem, $customer);

        if (! $manageSubscription) {
            return $subscription;
        }

        $this->provisionService->provision([$subscription]);

        return $subscription;
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function getSubscriptionsQuery(): SubscriptionQueryBuilder
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        return Subscription::query()->where('customer_id', $customer->id);
    }

    public function updateSubscriptionStatus(
        string $domain,
        string $status,
        string $message,
        bool $sendFailedMailToCustomer = true,
    ): void {
        $subscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup(
            $domain,
            ProductGroupType::EXTENSION,
        );

        $subscription->technical_status = $status;
        $subscription->save();

        if (
            $sendFailedMailToCustomer
            && ($status === TechnicalStatus::PENDING->value || $status === TechnicalStatus::FAILED->value)
        ) {
            $this->sendFailedDomainEmail($subscription, $domain, $message);
        }
    }

    public function updateSubscription(Subscription $subscription, SubscriptionUpdateRequestDTO $values): void
    {
        if ($subscription->net_price !== $values->net_price) {
            $this->pricePersistService->persistCustomPrice(
                $subscription,
                $values->net_price,
                true,
                CustomPriceReasonType::MANUAL_COMPASS_OVERRIDE,
            );
        }

        $subscription->domain = $values->domain;
        $subscription->gross_price = $values->gross_price;
        $subscription->net_price = $values->net_price;
        $subscription->product_uuid = $values->product->uuid;
        $subscription->administrative_status = $values->administrative_status->value;
        $subscription->technical_status = $values->technical_status->value;
        $subscription->save();
    }

    public function createFreeDnsSubscriptionForExtension(
        Subscription $subscription,
        ?string $administrative_status = null,
        ?string $technical_status = null,
    ): ?Subscription {
        if (Subscription::query()
            ->whereProductGroupType(ProductGroupType::DNS)
            ->where('domain', $subscription->domain)
            ->whereNotIn('administrative_status', [
                AdministrativeStatus::ARCHIVED->value,
                AdministrativeStatus::ARCHIVING->value,
            ])
            ->exists()) {
            return null;
        }

        $freeDnsProduct = $this->productRepository->findProductByProductGroupSlugAndWildcardProductSlug(
            ProductGroupType::DNS,
            '%free-%',
        );

        Assert::notNull($freeDnsProduct, 'Free DNS product could not be found');

        $freeDnsSubscription = new Subscription();
        $freeDnsSubscription->uuid = Uuid::uuid4()->toString();
        $freeDnsSubscription->parent_subscription_id = $subscription->id;
        $freeDnsSubscription->customer_id = $subscription->customer_id;
        $freeDnsSubscription->product_uuid = $freeDnsProduct->uuid;
        $freeDnsSubscription->domain = $subscription->domain;
        $freeDnsSubscription->technical_status = $technical_status ?? TechnicalStatus::OK->value;
        $freeDnsSubscription->administrative_status = $administrative_status ?? $subscription->administrative_status;
        $freeDnsSubscription->billing_period = $subscription->billing_period;
        $freeDnsSubscription->contract_period = $subscription->contract_period;
        $freeDnsSubscription->cancel_date = $subscription->cancel_date;
        $freeDnsSubscription->gross_price = 0;
        $freeDnsSubscription->net_price = 0;
        $freeDnsSubscription->start_date = $subscription->start_date;
        $freeDnsSubscription->end_date = $subscription->end_date;
        $freeDnsSubscription->next_billing_date = $subscription->next_billing_date;
        // We don't want any event or observers to be triggered
        // See https://yh-jira.atlassian.net/browse/WATER-5308
        $freeDnsSubscription->saveQuietly();

        $price = new Price(
            ProductPriceType::REGISTRATION,
            $freeDnsSubscription->billing_period,
            $freeDnsProduct->id,
            $freeDnsProduct->productGroup->uuid,
            0,
            $freeDnsSubscription->contract_period,
            false,
            false,
            appliedPriceComponents: [new RegistrationPriceComponent(0)],
            calculatedPrice: 0,
        );
        $this->pricePersistService->persistSubscriptionPrice(
            $freeDnsSubscription,
            $price,
            $freeDnsSubscription->start_date,
        );

        return $freeDnsSubscription;
    }

    public function createChildSubscription(Subscription $parentSubscription, Product $product): Subscription
    {
        $productPrice = $this->getProductPrice($product, $parentSubscription);
        Assert::natural($productPrice->calculatedPrice);

        $subscription = new Subscription();

        $subscription->product_uuid = $product->uuid;
        $subscription->customer_id = $parentSubscription->customer_id;
        $subscription->domain = $parentSubscription->domain;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->billing_period = $parentSubscription->billing_period;
        $subscription->contract_period = $parentSubscription->contract_period;
        $subscription->gross_price = $productPrice->regularPrice;
        $subscription->net_price = $productPrice->calculatedPrice;
        $subscription->start_date = $parentSubscription->start_date;
        $subscription->next_billing_date = $parentSubscription->next_billing_date;
        $subscription->end_date = $parentSubscription->end_date;
        $subscription->parent_subscription_id = $parentSubscription->id;

        $subscription->save();

        return $subscription;
    }

    public function createFreeParentSubscription(Subscription $childSubscription, Product $product): Subscription
    {
        $subscription = new Subscription();

        $subscription->product_uuid = $product->uuid;
        $subscription->customer_id = $childSubscription->customer_id;
        $subscription->domain = $childSubscription->domain;
        $subscription->technical_status = TechnicalStatus::REGISTRATION->value;
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->billing_period = $childSubscription->billing_period;
        $subscription->contract_period = $childSubscription->contract_period;
        $subscription->gross_price = 0;
        $subscription->net_price = 0;
        $subscription->start_date = $childSubscription->start_date;
        $subscription->next_billing_date = $childSubscription->next_billing_date;
        $subscription->end_date = $childSubscription->end_date;

        $subscription->save();

        $price = new Price(
            ProductPriceType::REGISTRATION,
            $childSubscription->billing_period,
            $product->id,
            $product->productGroup->uuid,
            0,
            $childSubscription->contract_period,
            false,
            false,
            appliedPriceComponents: [new RegistrationPriceComponent(0)],
            calculatedPrice: 0,
        );
        $this->pricePersistService->persistSubscriptionPrice($subscription, $price, $subscription->start_date);

        return $subscription;
    }

    private function createSubscription(OrderLineItem $orderLineItem, Customer $customer): Subscription
    {
        assert($orderLineItem->gross_price >= 0);
        assert($orderLineItem->net_price >= 0);

        $subscription = new Subscription();
        $subscription->uuid = $orderLineItem->subscription_uuid ?? Str::uuid()->toString();
        $subscription->parent_subscription_id = $orderLineItem->parentSubscription?->id;

        if ($orderLineItem->parent_id !== null) {
            $orderLineItemParent = OrderLineItem::where('id', $orderLineItem->parent_id)->firstOrFail();
            $parentSubscription = Subscription::where('uuid', $orderLineItemParent->subscription_uuid)->firstOrFail();
            $subscription->parent_subscription_id = $parentSubscription->id;
        }

        $subscription->customer_id = $customer->id;
        $subscription->contract_period = $orderLineItem->contract_period;
        $subscription->billing_period = $orderLineItem->billing_period;
        $subscription->start_date = CarbonImmutable::now();
        $subscription->end_date = CarbonImmutable::now()->addMonths($orderLineItem->contract_period);
        $subscription->next_billing_date = CarbonImmutable::now()->addMonths($orderLineItem->billing_period);
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->technical_status = $orderLineItem->status->value;
        $subscription->gross_price = $orderLineItem->gross_price;
        $subscription->net_price = $orderLineItem->net_price;
        $subscription->product_uuid = $orderLineItem->product_uuid;
        $subscription->domain = is_string($orderLineItem->domain) ? strtolower($orderLineItem->domain) : null;
        $subscription->save();

        $this->pricePersistService->persistSubscriptionPriceFromOrderLine($subscription, $orderLineItem);

        if ($orderLineItem->experiment_slug !== null) {
            $experiment = $this->experimentRepository->getBySlug($orderLineItem->experiment_slug);
            $experiment->subscriptions()->attach($subscription);
        }

        return $subscription;
    }

    private function sendFailedDomainEmail(Subscription $subscription, string $domain, string $message): void
    {
        $this->mailer->send(
            [$subscription->customer],
            new MailDomainCreationFailed(
                $domain,
                $message,
            ),
        );
    }

    private function getProductPrice(Product $product, Subscription $subscription): Price
    {
        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $subscription->customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        return $priceList->getProductPrice(
            $product->slug,
            $subscription->contract_period,
            $subscription->billing_period,
        );
    }
}
