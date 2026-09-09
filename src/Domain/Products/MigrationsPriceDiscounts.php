<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent as PriceModel;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Exceptions\ProductPriceNotDiscountableException;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Webmozart\Assert\Assert;

/**
 * @internal Only for use with the ferry Migration stack.
 */
class MigrationsPriceDiscounts
{
    public function __construct(private readonly VolumeDiscountService $productDiscountService)
    {
    }

    public function assignDiscount(
        int $discountPrice,
        int $contractPeriod,
        int $billingPeriod,
        Price $price,
        Customer $customer
    ): void {
        Assert::natural($discountPrice);

        if ($price->billingPeriod !== $billingPeriod || $price->contractPeriod !== $contractPeriod) {
            throw new ProductPriceNotDiscountableException(sprintf(
                'Discount periods (billing: %d, contract: %d) do not match base price entity periods (billing: %d, contract: %d)',
                $billingPeriod,
                $contractPeriod,
                $price->billingPeriod,
                $price->contractPeriod,
            ));
        }

        if (! $this->isDiscountablePrice($discountPrice, $price)) {
            // If we have a case in which we don't want to have a discount setup we'll just continue like usual!
            throw new ProductPriceNotDiscountableException(sprintf(
                'Given price: %d customer: %d pricing could not be applied.',
                $discountPrice,
                $customer->id,
            ));
        }

        if ($this->alreadyHasDiscount($customer, $price->productId, $contractPeriod, $billingPeriod)) {
            // If a discount already exists for this customer and product with the relevant pricing data (type, periods)
            // then continue like usual.
            return;
        }

        $migratedCustomer = $customer->migratedCustomers->firstOrFail();

        $discount = new ProductDiscount();
        $discount->product_id = $price->productId;
        $discount->name = sprintf('migratedCustomer : %s', $migratedCustomer->reference_customer_number);
        $discount->description = 'Continues discount for a migrated customer';
        $discount->save();

        $discount->customers()->attach($customer);

        $priceModel = new PriceModel();
        $priceModel->type = PriceComponentType::PROLONGATION_STAFFEL;
        $priceModel->product_id = $price->productId;
        $priceModel->price = $discountPrice;
        $priceModel->contract_period = $contractPeriod;
        $priceModel->billing_period = $billingPeriod;
        $priceModel->orderable = true;
        $priceModel->starts_at = CarbonImmutable::now();
        $priceModel->save();

        $this->productDiscountService->attachPrice($discount, $priceModel);
    }

    public function isDiscountablePrice(int $discountPrice, Price $price): bool
    {
        // If the prices as the same as another. For example, we are migrating a product that costs the same if waterfront
        // as the price in the migration subscription payload we don't want to apply any discounts.
        if ($discountPrice === $price->regularPrice) {
            Log::warning(sprintf(
                'Given price %d was the same as the base price model for product id %d',
                $discountPrice,
                $price->productId
            ));

            return false;
        }

        // If the given price is higher that the default price we also don't want to create any discounts
        if ($discountPrice > $price->regularPrice) {
            Log::warning(sprintf(
                'Given price %d was higher than the base price model with regular price %d product id %d',
                $discountPrice,
                $price->regularPrice,
                $price->productId
            ));

            return false;
        }

        /**
         * Products can be free, therefore we can not apply a discount.
         * For the time being Products can be negative. WATER-4548.
         */
        if ($price->regularPrice <= 0) {
            Log::warning(sprintf(
                'Given base price model with regular price %d product id %d was either already free or negative',
                $price->regularPrice,
                $price->productId
            ));

            return false;
        }

        return true;
    }

    private function alreadyHasDiscount(Customer $customer, int $productId, int $contractPeriod, int $billingPeriod): bool
    {
        $discount = DB::select(<<<SQL
            select p.id
            from product_price_components p
            join product_discount_prices pdp
            on p.id = pdp.price_id
            join customer_product_discount cpd
            on pdp.product_discount_id = cpd.product_discount_id
            where product_id = :productId
            and customer_id = :customerId
            and type = :priceType
            and contract_period = :contractPeriod
            and billing_period = :billingPeriod
            and starts_at <= :currentDate
            and (expires_at is null or expires_at > :currentDate)
            SQL, ['currentDate' => CarbonImmutable::now(), 'priceType' => PriceComponentType::PROLONGATION_STAFFEL->value, 'customerId' => $customer->id, 'productId' => $productId, 'contractPeriod' => $contractPeriod, 'billingPeriod' => $billingPeriod]);

        return count($discount) > 0;
    }
}
