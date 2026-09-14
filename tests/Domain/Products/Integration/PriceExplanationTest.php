<?php

declare(strict_types=1);

namespace Tests\Domain\Products\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPeriodFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Atlantis\Resources\Products\PriceResource;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;

#[CoversClass(PriceResolver::class)]
class PriceExplanationTest extends IntegrationTestCase
{
    private PriceResolver $priceResolver;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->priceResolver = self::resolve(PriceResolver::class);
    }

    #[Test]
    public function priceExplanations(): void
    {
        $productGroup = new ProductGroupFactory()->createOne(['name' => 'Hosting Products', 'slug' => 'hosting']);
        $product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Test 1',
            'slug' => 'test-1',
            'weight' => 1,
        ]);

        $language1 = new TranslationLanguageFactory()->createOne(['locale' => 'en']);
        $language2 = new TranslationLanguageFactory()->createOne(['locale' => 'nl']);

        $priceExplanation = new TranslationKeyFactory()
            ->withTranslatedString($language1, 'test translation')
            ->withTranslatedString($language2, 'test vertaling')
            ->createOne(['key' => 'test.price.explanation']);

        new ProductPeriodFactory()
            ->for($product)
            ->for($priceExplanation, 'priceExplanation')
            ->createOne(['contract_period' => 12, 'billing_period' => 12]);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 10,
            ]);
        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne([
                'contract_period' => 6,
                'billing_period' => 6,
                'price' => 10,
            ]);

        $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], null);
        $priceList = $this->priceResolver->getPriceList($priceRequest);

        $priceWithExplanation = $priceList->getProductPrice('test-1', 12, 12);
        $priceResource = PriceResource::make($priceWithExplanation)->toArray(Request::create('/'));
        self::assertInstanceOf(Collection::class, $priceResource['price_explanation']);
        self::assertCount(2, $priceResource['price_explanation']);
        self::assertSame('test translation', $priceResource['price_explanation']['en']);
        self::assertSame('test vertaling', $priceResource['price_explanation']['nl']);

        $priceWithoutExplanation = $priceList->getProductPrice('test-1', 6, 6);
        $priceResource = PriceResource::make($priceWithoutExplanation)->toArray(Request::create('/'));
        self::assertNull($priceResource['price_explanation']);
    }
}
