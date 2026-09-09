<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Rules;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ItemNotFoundException;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\MigrationsPriceDiscounts;
use Waterfront\Domain\Products\Models\Product as ProductModel;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Infra\Translation\Translator;
use Waterfront\Infra\Validation\AbstractValidator;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

/**
 * Rule for validating that a product price is correct. Both the net and gross price are validated.
 */
class MigrationCustomerCanCreateDiscount extends AbstractValidator
{
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly Translator $translator,
        private readonly MigrationsPriceDiscounts $migrationsPriceDiscounts
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (
            ! is_array($value) ||
            ! array_key_exists('contract_period', $value) ||
            ! array_key_exists('billing_period', $value) ||
            ! array_key_exists('slug', $value) ||
            ! array_key_exists('price', $value)
        ) {
            Log::warning('Product configuration is invalid.');
            return false;
        }

        $contractPeriod = Arr::get($value, 'contract_period');
        $billingPeriod = Arr::get($value, 'billing_period');
        $slug = Arr::get($value, 'slug');
        $price = Arr::get($value, 'price');

        Assert::integer($billingPeriod);
        Assert::integer($contractPeriod);
        Assert::integer($price);
        Assert::string($slug);

        $product = ProductModel::query()->where('slug', $slug)->first();

        if (! $product instanceof ProductModel) {
            return false;
        }

        try {
            // This needs to be resolvable in migrations to verify we can give a pricing to the given slug.
            $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], null);
            $priceDTOResult = $this
                ->priceResolver
                ->getPriceList($priceRequest)
                ->getProductPrice(productSlug: $slug, contractPeriod: $contractPeriod, billingPeriod: $billingPeriod);

            return $this->isDiscount($price, $priceDTOResult);
        } catch (ItemNotFoundException $exception) {
            Log::warning('Product price could not be resolved.', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PRODUCT_SLUG => $slug,
                LoggingContextKeys::META => [
                    'contract_period' => $contractPeriod,
                    'billing_period' => $billingPeriod,
                    'price_type' => ProductPriceType::PROLONGATION->value,
                ],
            ]);
            return false;
        }
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.migration_discount_validation');
    }

    private function isDiscount(int $price, Price $priceDTOResult): bool
    {
        return $this->migrationsPriceDiscounts->isDiscountablePrice($price, $priceDTOResult);
    }
}
