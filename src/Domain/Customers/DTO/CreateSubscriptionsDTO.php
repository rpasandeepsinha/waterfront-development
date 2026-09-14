<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Customers\Models\Customer;

class CreateSubscriptionsDTO
{
    /**
     * @var array<CreateSubscriptionDTO>
     */
    private array $subscriptions = [];

    /**
     * @var array<ImplementableProducts>
     */
    private array $implementableProducts = [];

    public function __construct(
        private readonly Customer $customer,
        private readonly string $referenceCustomerId,
    ) {
    }

    /**
     * @param mixed[] $subscriptions
     */
    public static function create(
        Customer $customer,
        string $referenceCustomerId,
        array $subscriptions,
    ): self {
        $subscriptionsDto = new self($customer, $referenceCustomerId);

        foreach ($subscriptions as $implementableProduct => $subscription) {
            // @phpstan-ignore-next-line
            if (count($subscription) < 1) {
                continue;
            }

            $subscriptionsDto->addImplementableProduct(ImplementableProducts::from($implementableProduct));

            foreach ($subscription as $item) {
                assert(is_array($item));

                $slug = $item['slug'] ?? null;
                $billingPeriod = $item['billing_period'] ?? null;
                $contractPeriod = $item['contract_period'] ?? null;
                $extension = $item['extension'] ?? null;

                assert(is_string($slug));
                assert(is_int($billingPeriod));
                assert(is_int($contractPeriod));
                assert(is_string($extension) || is_null($extension));

                $fixedPrice = null;
                $fixedPriceIsOneOff = true;
                if (
                    array_key_exists('reference_net_price_is_fixed', $item)
                    && $item['reference_net_price_is_fixed'] === true
                    && array_key_exists('reference_net_price', $item)
                    && is_int($item['reference_net_price'])
                ) {
                    $fixedPrice = $item['reference_net_price'];
                    $fixedPriceIsOneOff =
                        array_key_exists('reference_net_price_is_one_off', $item)
                        && is_bool($item['reference_net_price_is_one_off'])
                        && $item['reference_net_price_is_one_off'];
                }

                $subscriptionDto = new CreateSubscriptionDTO(
                    domain: $item['domain'] ?? null,
                    extension: $extension,
                    slug: $slug,
                    billingPeriod: $billingPeriod,
                    contractPeriod: $contractPeriod,
                    fixedPrice: $fixedPrice,
                    fixedPriceIsOneOff: $fixedPriceIsOneOff,
                    referenceSubscriptionId: $item['reference_subscription_id'],
                    referenceProductId: $item['reference_product_id'],
                    internalComment: $item['internal_comment'] ?? null,
                    startDate: new CarbonImmutable($item['start_date']),
                    nextContractDate: new CarbonImmutable($item['next_contract_date']),
                    nextBillingDate: new CarbonImmutable($item['next_billing_date']),
                    cancelDate: array_key_exists('cancel_date', $item) && ! is_null($item['cancel_date'])
                        ? new CarbonImmutable($item['cancel_date'])
                        : null,
                    labels: $item['labels'] ?? null,
                    createSubscriptions: $subscriptionsDto,
                    implementableProduct: ImplementableProducts::from($implementableProduct),
                );
                $subscriptionsDto->addSubscription($subscriptionDto);
            }
        }

        return $subscriptionsDto;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getReferenceCustomerId(): string
    {
        return $this->referenceCustomerId;
    }

    /**
     * @return array<CreateSubscriptionDTO>
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    public function hasDomainSubscriptions(): bool
    {
        return in_array(ImplementableProducts::DOMAIN_EXTENSION, $this->implementableProducts, true);
    }

    public function hasHostingSubscriptions(): bool
    {
        return in_array(ImplementableProducts::HOSTING, $this->implementableProducts, true);
    }

    public function hasMailOnlySubscriptions(): bool
    {
        return in_array(ImplementableProducts::MAIL_ONLY, $this->implementableProducts, true);
    }

    public function hasSitebuilderSubscriptions(): bool
    {
        return in_array(ImplementableProducts::SITEBUILDER, $this->implementableProducts, true);
    }

    public function hasSslSubscriptions(): bool
    {
        return in_array(ImplementableProducts::SSL, $this->implementableProducts, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        $collection = [];

        foreach ($this->subscriptions as $createSubscription) {
            $collection[] = $createSubscription->toArray();
        }

        return $collection;
    }

    private function addSubscription(CreateSubscriptionDTO $createSubscription): void
    {
        $this->subscriptions[] = $createSubscription;
    }

    private function addImplementableProduct(ImplementableProducts $implementableProduct): void
    {
        $this->implementableProducts[] = $implementableProduct;
    }
}
