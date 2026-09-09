<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\ItemNotFoundException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\Mailer\MailDowngradeProduct;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailUpgradeProduct;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Products\Services\PriceService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Domain\Subscriptions\Exceptions\DowngradeCancelException;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionChangeException;
use Waterfront\Domain\Subscriptions\Exceptions\TerminateException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class SubscriptionChangeService
{
    public function __construct(
        private Dispatcher $eventDispatcher,
        private MailerInterface $mailer,
        private ProductSpecRepository $productSpecRepository,
        private SubscriptionRepository $subscriptionRepo,
        private ProductAllowedChangeRepository $allowedChangeRepository,
        private ProductRepository $productRepository,
        private LoggerInterface $logger,
        private PriceService $priceService,
        private ChangeDnsAction $changeDnsAction,
        private CancellationService $cancellationService,
        private SubscriptionTerminateService $terminateService,
        private PriceResolver $priceResolver,
        private PriceRepository $priceRepository,
        private PricePersistService $pricePersistService,
    ) {
    }

    public function shouldDowngradeSubscriptionWithParent(Subscription $subscription): bool
    {
        if ($subscription->parent_subscription_id === null) {
            return false;
        }

        assert($subscription->parent instanceof Subscription);

        $downgradeWhenCancelledSpec = $this->productSpecRepository->findBySpecification(
            $subscription->product,
            ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value
        );

        if (! $downgradeWhenCancelledSpec instanceof ProductSpec) {
            return false;
        }

        if ($this->subscriptionRepo->isExpired($subscription->parent)) {
            return false;
        }

        return true;
    }

    public function downgradeCanceled(Subscription $subscription): void
    {
        $this->logger->debug('Downgrading subscription instead of terminating.', [
            LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
        ]);

        try {
            $downgradeProduct = $this->getAvailableDowngradeWhenCanceled($subscription);

            $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
            $subscription->termination_date = null;
            $subscription->cancel_date = null;
            $subscription->cancel_reason = null;
            $subscription->save();

            match ($subscription->product->productGroup->slug) {
                ProductGroupType::DNS => $this->changeDnsAction->execute($subscription, ProductChangeType::DOWNGRADE),
                ProductGroupType::EXTENSION,
                ProductGroupType::REDIRECT,
                ProductGroupType::OTHER,
                ProductGroupType::SSL,
                ProductGroupType::RESELLER_HOSTING,
                ProductGroupType::VPS,
                ProductGroupType::MANUAL_SUBSCRIPTION,
                ProductGroupType::HOSTING,
                ProductGroupType::DOMAIN_EXPANSION,
                ProductGroupType::RESELLER_DISCOUNT,
                ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
                ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
                ProductGroupType::CLOUDSTACK_VOLUME,
                ProductGroupType::CLOUDSTACK_OS,
                ProductGroupType::MICROSOFT_365,
                ProductGroupType::ONE_TIME_SERVICE,
                ProductGroupType::VOLUME_DISCOUNT,
                ProductGroupType::BACKUP,
                ProductGroupType::ADD_ON => null
            };

            $this->change(
                changeType: ProductChangeType::DOWNGRADE,
                subscription: $subscription,
                newProduct: $downgradeProduct
            );
        } catch (DowngradeCancelException $e) {
            $this->logger->error('Downgrading subscription instead of terminating contains an error and is therefore not executed', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::EXCEPTION => $e,
            ]);
        }
    }

    public function createUpgradeMutation(
        Subscription $subscription,
        Product $newProduct,
        ?PriceComponent $priceComponent,
    ): SubscriptionMutation {
        $subscriptionMutation = new SubscriptionMutation();
        $subscriptionMutation->product_id = $newProduct->id;

        $newProductPrice = $this->getNewProductPrice(
            changeType: ProductChangeType::UPGRADE,
            subscription: $subscription,
            newProduct: $newProduct
        );

        if ($priceComponent !== null) {
            $subscriptionMutation->net_price = $priceComponent->newPrice;
        } else {
            Assert::natural($newProductPrice->calculatedPrice);
            $subscriptionMutation->net_price = $newProductPrice->calculatedPrice;
        }

        $subscriptionMutation->gross_price = $newProductPrice->regularPrice;
        $subscriptionMutation->contract_period = $subscription->contract_period;
        $subscriptionMutation->billing_period = $subscription->billing_period;
        $subscriptionMutation->mutated_at = CarbonImmutable::now();
        $subscriptionMutation->subscription_id = $subscription->id;
        $subscriptionMutation->process_technical_at = null;
        $subscriptionMutation->processed_technical_at = null;
        $subscriptionMutation->save();

        return $subscriptionMutation;
    }

    /**
     * @throws SubscriptionChangeException|TerminateException
     */
    public function change(
        ProductChangeType $changeType,
        Subscription $subscription,
        Product $newProduct,
        bool $invoiceTheChange = true,
        bool $sendMail = true
    ): SubscriptionChange {
        $newProductPrice = $this->getNewProductPrice(
            changeType: $changeType,
            subscription: $subscription,
            newProduct: $newProduct
        );

        $currentSubscriptions = [$subscription];

        $comesWithFreeProduct = $this->productRepository->comesWithFreeProduct($newProduct);

        if ($comesWithFreeProduct instanceof Product) {
            $childrenToTerminate = $subscription->children()->where('product_uuid', $comesWithFreeProduct->uuid)->get();

            if (count($childrenToTerminate) > 0) {
                foreach ($childrenToTerminate as $childToTerminate) {
                    $this->cancellationService->cancel(
                        subscription: $childToTerminate,
                        cancelType: SubscriptionCancelType::CANCEL_OTHER,
                        cancelReason: SubscriptionCancelReason::REASON_OTHER,
                        sendMail: false,
                        cancelTypeOtherDate: CarbonImmutable::now(),
                        saveNote: false,
                    );

                    $this->terminateService->terminate($subscription);

                    $currentSubscriptions[] = $childToTerminate;
                }
            }
        }

        // We need to get the price to charge before updating the subscription
        $charge = null;
        if ($invoiceTheChange) {
            $charge = $this->charge(
                changeType: $changeType,
                subscriptions: $currentSubscriptions,
                newProductPrice: $newProductPrice
            );
        }

        $currentProduct = $subscription->product;

        // There might be precalculated subscription prices for future billing cycles. Those are no longer valid,
        // because they were for the previous product. The result is that the subscription net_price is used for
        // the following billing cycles.
        $this->priceRepository->deleteFutureSubscriptionPrices($subscription);

        // Perform the administrative up- or downgrade.
        $subscription->product_uuid = $newProduct->uuid;
        $subscription->net_price = max(0, $newProductPrice->calculatedPrice);
        $subscription->gross_price = $newProductPrice->regularPrice;
        $subscription->save();

        $this->pricePersistService->persistSubscriptionPrice($subscription, $newProductPrice, CarbonImmutable::now());

        $subscription->refresh();

        if ($invoiceTheChange) {
            $this->eventDispatcher->dispatch(
                new SubscriptionChangedEvent(
                    subscription: $subscription,
                    charge: $charge,
                    changeType: $changeType
                )
            );
        }

        $changeObject = new SubscriptionChange();
        $changeObject->uuid = Uuid::uuid4();
        $changeObject->subscription_uuid = Uuid::fromString($subscription->uuid);
        $changeObject->from_product_uuid = Uuid::fromString($currentProduct->uuid);
        $changeObject->to_product_uuid = Uuid::fromString($subscription->product->uuid);
        $changeObject->type = $changeType;
        $changeObject->status = SubscriptionChangeStatus::COMPLETED;
        $changeObject->requested_at = CarbonImmutable::now();
        $changeObject->completed_at = CarbonImmutable::now();
        $changeObject->save();

        if (! $sendMail) {
            return $changeObject;
        }

        if ($changeType === ProductChangeType::DOWNGRADE) {
            $this->mailer->send([$subscription->customer], new MailDowngradeProduct(
                $subscription->domain ?? '',
                $subscription->product->name,
                $subscription->contract_period,
                $subscription->gross_price ?? 0,
            ));
        } elseif ($changeType === ProductChangeType::UPGRADE) {
            $this->mailer->send([$subscription->customer], new MailUpgradeProduct(
                $subscription->product->name,
                $subscription->domain ?? '',
                $subscription->contract_period,
                $subscription->gross_price ?? 0,
            ));
        }

        return $changeObject;
    }

    /**
     * @param array<Subscription> $subscriptions
     */
    public function charge(ProductChangeType $changeType, array $subscriptions, Price $newProductPrice): int
    {
        Assert::notNull($newProductPrice->calculatedPrice);

        return match ($changeType) {
            // We don't credit on an upgrade.
            ProductChangeType::UPGRADE => array_reduce(
                $subscriptions,
                fn ($remaining, Subscription $subscription) => $remaining + $this->priceService->calculateProRate($subscription->net_price, $newProductPrice->calculatedPrice, $subscription->next_billing_date, $subscription->billing_period),
                0
            ),
            // We don't refund on a downgrade.
            ProductChangeType::DOWNGRADE,
            ProductChangeType::REINSTALL => 0,
        };
    }

    /**
     * Catching invalidArgumentException filtering the 'wrong' upgrade from the array.
     *
     * @return Collection<int, array{product: mixed[], full_charge: int, charge: int}>
     */
    public function getPotentialChanges(ProductChangeType $changeType, Subscription $subscription): Collection
    {
        $potentialChanges = $changeType === ProductChangeType::UPGRADE
            ? $this->allowedChangeRepository->getPotentialUpgradesForCustomer(product: $subscription->product)
            : $this->allowedChangeRepository->getPotentialDowngradesForCustomer(product: $subscription->product);

        $validatedChanges = new Collection();

        foreach ($potentialChanges as $potentialChangeProduct) {
            try {
                $newProductPrice = $this->getNewProductPrice(
                    changeType: $changeType,
                    subscription: $subscription,
                    newProduct: $potentialChangeProduct
                );

                $validatedChanges->push(
                    [
                        'product' => $potentialChangeProduct->toArray(),
                        'full_charge' => $newProductPrice->calculatedPrice,
                        'charge' => $this->charge($changeType, [$subscription], $newProductPrice),
                    ]
                );
            } catch (SubscriptionChangeException | ModelNotFoundException $exception) {
                $this->logger->warning(
                    sprintf(
                        'Product with UUID "%s" was filtered out from upgrade list for subscription with UUID "%s".',
                        $potentialChangeProduct->uuid,
                        $subscription->uuid,
                    ),
                    [
                        LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                        LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ]
                );
                continue;
            }
        }

        return $validatedChanges;
    }

    /**
     * @throws SubscriptionChangeException
     */
    public function getNewProductPrice(ProductChangeType $changeType, Subscription $subscription, Product $newProduct): Price
    {
        $potentialProducts = match ($changeType) {
            ProductChangeType::UPGRADE => $this->allowedChangeRepository->getPotentialUpgrades($subscription->product),
            ProductChangeType::DOWNGRADE => $this->allowedChangeRepository->getPotentialDowngrades($subscription->product),
            ProductChangeType::REINSTALL => $this->allowedChangeRepository->getPotentialReinstalls($subscription->product),
        };

        if ($potentialProducts->where('uuid', $newProduct->uuid)->isEmpty()) {
            throw SubscriptionChangeException::noPotentialProducts(
                subscriptionUuid: $subscription->uuid,
                productName: $newProduct->name
            );
        }

        try {
            $priceRequest = new PriceRequest([new ProlongationPriceRequest($newProduct)], $subscription->customer);
            $priceList = $this->priceResolver->getPriceList($priceRequest);
            $newProductPrice = $priceList->getProductPrice($newProduct->slug, $subscription->contract_period, $subscription->billing_period);
        } catch (ItemNotFoundException) {
            throw SubscriptionChangeException::noProlongationProductPrice(
                productName: $newProduct->name,
                billingPeriod: $subscription->billing_period,
                contractPeriod: $subscription->contract_period,
            );
        }

        return $newProductPrice;
    }

    /**
     * @throws DowngradeCancelException
     */
    public function getAvailableDowngradeWhenCanceled(Subscription $subscription): Product
    {
        $productSpec = $this->productSpecRepository->findBySpecification(
            product: $subscription->product,
            specification: ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value
        );

        if (! $productSpec instanceof ProductSpec) {
            throw new DowngradeCancelException(sprintf(
                'Downgrade not possible for product "%s" with %d due to the absence of the correct product spec %s',
                $subscription->product->slug,
                $subscription->product->id,
                ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value
            ));
        }

        /** @var string $productSpecValue */
        $productSpecValue = $productSpec->value;
        $product = $this->productRepository->findProductBySlug($productSpecValue);

        if (! $this->allowedChangeRepository->isProductChangeAllowed(
            changeType: ProductChangeType::DOWNGRADE,
            fromProduct: $subscription->product,
            toProduct: $product
        )) {
            throw new DowngradeCancelException(sprintf(
                'Downgrade not possible for product "%s": target product "%s" is not a downgrade possibility',
                $subscription->product->slug,
                $product->slug,
            ));
        }

        return $product;
    }

    public function storeChangeRecord(
        Subscription $subscription,
        Product $newProduct,
        ProductChangeType $changeType,
        SubscriptionChangeStatus $status
    ): void {
        $change = new SubscriptionChange();
        $change->uuid = Uuid::uuid4();
        $change->subscription_uuid = Uuid::fromString($subscription->uuid);
        $change->from_product_uuid = Uuid::fromString($subscription->product_uuid);
        $change->to_product_uuid = Uuid::fromString($newProduct->uuid);
        $change->type = $changeType;
        $change->status = $status;
        $change->requested_at = CarbonImmutable::now();
        $change->save();
    }
}
