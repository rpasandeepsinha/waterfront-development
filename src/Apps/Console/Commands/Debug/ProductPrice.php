<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Debug;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Pricing\DTO\PriceComponents\PriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;

#[AsCommand(name: 'debug:product-price')]
#[Description(
    'Output all internal information about configured prices for a product given a specific scenario (default=registration)',
)]
#[Signature('debug:product-price {productSlug} {scenario=registration}')]
class ProductPrice extends Command
{
    public function handle(
        PriceResolver $priceResolver,
        ProductRepository $productRepository,
    ): int {
        /** @var string $scenario */
        $scenario = $this->argument('scenario');
        /** @var string $productSlug */
        $productSlug = $this->argument('productSlug');

        $scenario = ProductPriceType::from($scenario);
        $product = $productRepository->findProductBySlug($productSlug);

        $productRequest = match ($scenario) {
            ProductPriceType::REGISTRATION => new RegistrationPriceRequest($product),
            ProductPriceType::PROLONGATION => new ProlongationPriceRequest($product),
        };

        $priceList = $priceResolver->getPriceList(new PriceRequest([$productRequest], null));
        /** @var Price[] $productPrices */
        $productPrices = $priceList->where('slug', $productSlug)->firstOrFail()->prices->toArray();

        $this->table(
            [
                'Type',
                'Product',
                'Contract',
                'Billing',
                'Regular',
                'Introduction',
                'Calculated',
                'Orderable',
                'Possible price components',
                'Applied price components',
            ],
            array_map(fn (Price $price) => [
                $price->type->value,
                $price->productId,
                $price->contractPeriod,
                $price->billingPeriod,
                $price->regularPrice,
                $price->calculatedPrice,
                $price->orderable,
                implode(',', array_map(
                    fn (PriceComponent $component) => $component->type->value,
                    $price->possiblePriceComponents,
                )),
                implode(',', array_map(
                    fn (PriceComponent $component) => $component->type->value,
                    $price->appliedPriceComponents,
                )),
            ], $productPrices),
        );

        return self::SUCCESS;
    }
}
