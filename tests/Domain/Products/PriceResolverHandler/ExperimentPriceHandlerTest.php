<?php

declare(strict_types=1);

namespace Tests\Domain\Products\PriceResolverHandler;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ExperimentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ExperimentPriceLadderPriceComponent;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolverHandler\ExperimentPriceLadderPriceHandler;

#[CoversClass(ExperimentPriceLadderPriceHandler::class)]
class ExperimentPriceHandlerTest extends IntegrationTestCase
{
    private readonly ExperimentPriceLadderPriceHandler $handler;

    private readonly Product $product;

    private readonly Experiment $experiment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = self::resolve(ExperimentPriceLadderPriceHandler::class);

        $this->product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();
        $this->experiment = new ExperimentFactory()->createOne();
        $this->experiment->products()->attach($this->product->id);

        $this->priceLadderVariant($this->product, 321, CarbonImmutable::yesterday());
    }

    #[Test]
    public function addsThePriceLadderVariantToProlongationRequests(): void
    {
        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [
                $this->product->id => new ProlongationPriceRequest(
                    $this->product,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        $components = $prices->firstOrFail()->possiblePriceComponents;
        self::assertCount(1, $components);
        self::assertInstanceOf(ExperimentPriceLadderPriceComponent::class, $components[0]);
        self::assertSame(321, $components[0]->newPrice);
        self::assertNull($components[0]->appliedOrder);
    }

    #[Test]
    public function addsThePriceLadderVariantToRegistrationRequests(): void
    {
        $this->priceLadderVariant($this->product, 555, CarbonImmutable::yesterday());

        $prices = $this->handler->handle(
            new Collection([$this->registrationPrice($this->product->id)]),
            [
                $this->product->id => new RegistrationPriceRequest(
                    $this->product,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        $components = $prices->firstOrFail()->possiblePriceComponents;

        self::assertCount(1, $components);
        self::assertInstanceOf(ExperimentPriceLadderPriceComponent::class, $components[0]);
        self::assertSame(321, $components[0]->newPrice);
        self::assertNull($components[0]->appliedOrder);
    }

    #[Test]
    public function doesNothingWhenNoExperimentSlugProvided(): void
    {
        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [$this->product->id => new ProlongationPriceRequest($this->product)],
        );

        self::assertCount(0, $prices->firstOrFail()->possiblePriceComponents);
    }

    #[Test]
    public function doesNothingWhenThereIsNoRequestForTheProductAtAll(): void
    {
        $prices = $this->handler->handle(new Collection([$this->prolongationPrice($this->product->id)]), []);

        self::assertCount(0, $prices->firstOrFail()->possiblePriceComponents);
    }

    #[Test]
    public function doesNothingForARegistrationRequestEvenWhenTheProductIsInTheExperiment(): void
    {
        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [$this->product->id => new RegistrationPriceRequest($this->product)],
        );

        self::assertCount(0, $prices->firstOrFail()->possiblePriceComponents);
    }

    #[Test]
    public function doesNothingWhenTheNamedExperimentDoesNotCoverTheProduct(): void
    {
        $uncoveredProduct = new ProductFactory()->for(new ProductGroupFactory()->redirect())->createOne();
        $this->priceLadderVariant($uncoveredProduct, 555, CarbonImmutable::yesterday());

        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($uncoveredProduct->id)]),
            [
                $uncoveredProduct->id => new ProlongationPriceRequest(
                    $uncoveredProduct,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        self::assertCount(0, $prices->firstOrFail()->possiblePriceComponents);
    }

    #[Test]
    public function takesTheMostRecentlyStartedVariant(): void
    {
        // A previous version is in the setUp() method
        $this->priceLadderVariant($this->product, 111, CarbonImmutable::now());

        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [
                $this->product->id => new ProlongationPriceRequest(
                    $this->product,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        $components = $prices->firstOrFail()->possiblePriceComponents;
        self::assertCount(1, $components);
        self::assertSame(111, $components[0]->newPrice);
    }

    #[Test]
    public function ignoresVariantsThatHaveNotStartedYet(): void
    {
        $this->priceLadderVariant($this->product, 890, CarbonImmutable::tomorrow());

        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [
                $this->product->id => new ProlongationPriceRequest(
                    $this->product,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        $components = $prices->firstOrFail()->possiblePriceComponents;
        self::assertCount(1, $components);
        self::assertSame(321, $components[0]->newPrice);
    }

    #[Test]
    public function ignoresExpiredVariants(): void
    {
        $this->priceLadderVariant($this->product, 999, CarbonImmutable::now(), CarbonImmutable::now());

        $prices = $this->handler->handle(
            new Collection([$this->prolongationPrice($this->product->id)]),
            [
                $this->product->id => new ProlongationPriceRequest(
                    $this->product,
                    experimentSlug: $this->experiment->slug->value,
                ),
            ],
        );

        $components = $prices->firstOrFail()->possiblePriceComponents;
        self::assertCount(1, $components);
        self::assertSame(321, $components[0]->newPrice);
    }

    private function priceLadderVariant(
        Product $product,
        int $price,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $expiresAt = null,
    ): void {
        new ProductPriceComponentFactory()->for($product)->createOne([
            'type' => PriceComponentType::EXPERIMENT_PRICE_LADDER,
            'price' => $price,
            'contract_period' => 12,
            'billing_period' => 12,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    private function prolongationPrice(int $productId, int $contractPeriod = 12, int $billingPeriod = 12): Price
    {
        return $this->price($productId, ProductPriceType::PROLONGATION, $contractPeriod, $billingPeriod);
    }

    private function registrationPrice(int $productId, int $contractPeriod = 12, int $billingPeriod = 12): Price
    {
        return $this->price($productId, ProductPriceType::REGISTRATION, $contractPeriod, $billingPeriod);
    }

    private function price(int $productId, ProductPriceType $type, int $contractPeriod, int $billingPeriod): Price
    {
        return new Price(
            type: $type,
            billingPeriod: $billingPeriod,
            productId: $productId,
            productGroupUuid: 'uuid',
            regularPrice: 1234,
            contractPeriod: $contractPeriod,
            orderable: true,
            is_default: false,
        );
    }
}
