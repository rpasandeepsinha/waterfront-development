<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ItemNotFoundException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product as ProductModel;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Support\Enums\LoggingContextKeys;

/**
 * Rule for validating that a product price is correct. Both the net and gross price are validated.
 */
class MigrationCustomerHasProductAndPrice implements ValidationRule
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly LoggerInterface $logger,
        private readonly Customer $customer,
        private readonly ProductGroupType $productGroupType,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            ! is_array($value)
            || ! array_key_exists('contract_period', $value)
            || ! array_key_exists('billing_period', $value)
            || ! array_key_exists('slug', $value)
        ) {
            // The above fields are "required" in the MigrationValidationLibrary rules.
            $fail('Missing required fields (contract_period, billing_period, slug) for product price validation');

            return;
        }

        $contractPeriod = Arr::get($value, 'contract_period');
        $billingPeriod = Arr::get($value, 'billing_period');
        $slug = Arr::get($value, 'slug');
        $referenceNetPrice = Arr::get($value, 'reference_net_price');
        $referenceNetPriceIsFixed = Arr::get($value, 'reference_net_price_is_fixed');

        if (
            ! is_int($contractPeriod)
            || ! is_int($billingPeriod)
            || ! is_string($slug)
            || $referenceNetPrice !== null && ! is_int($referenceNetPrice)
        ) {
            // No message because the integer/string validation in the MigrationValidationLibrary handles this.
            return;
        }

        $product = ProductModel::query()
            ->whereHas(
                'productGroup',
                fn (Builder $query) => $query->where('slug', $this->productGroupType),
            )
            ->where('slug', $slug)
            ->first();

        if (! $product instanceof ProductModel) {
            // Don't fail because it'd be a duplicate message otherwise with
            // what's on the product slug field in MigrationValidationLibrary
            return;
        }

        try {
            // This needs to be resolvable in migrations to verify we can give a pricing to the given slug.
            $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $this->customer);
            $price = $this->priceResolver->getPriceList($priceRequest)->getProductPrice(
                productSlug: $slug,
                contractPeriod: $contractPeriod,
                billingPeriod: $billingPeriod,
            );

            // If the net price is fixed, there is no need to validate it. We do want to know
            // a price is resolvable for the product periods combination.
            if ($referenceNetPriceIsFixed === true) {
                return;
            }

            /**
             * Uses the defined net price from the pricing DTO with the following priority
             * and compares the resolved price from getNetPrice() to the (if given) reference price.
             *
             * With the purpose of ensuring that customers experience the same pricing as legacy labels.
             *
             * We want to validate the same price being used when creating the subscription.
             *
             * @see src/Domain/Customers/DTO/SubscriptionDTO.php
             */
            if ($referenceNetPrice !== null && $price->calculatedPrice !== $referenceNetPrice) {
                $fail(sprintf(
                    'Price is different for product %s and customer %d: new product price %d, reference product price %d',
                    $slug,
                    $this->customer->id,
                    $price->calculatedPrice,
                    $referenceNetPrice,
                ));

                return;
            }
        } catch (ItemNotFoundException $exception) {
            $this->logger->warning(
                'Product price could not be resolved.',
                [
                    LoggingContextKeys::CUSTOMER_ID => $this->customer->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::PRODUCT_SLUG => $slug,
                    LoggingContextKeys::META => [
                        'contract_period' => $contractPeriod,
                        'billing_period' => $billingPeriod,
                        'price_type' => ProductPriceType::PROLONGATION->value,
                    ],
                ],
            );

            $fail($exception->getMessage());

            return;
        }
    }
}
