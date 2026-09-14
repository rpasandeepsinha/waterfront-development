<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Validators;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\CartValidatorFactory;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(CartValidatorFactory::class)]
#[AllowMockObjectsWithoutExpectations]
class OrderValidationTest extends IntegrationTestCase
{
    private CartValidatorFactory $validatorFactory;

    /**
     * @var MockObject&RtrService
     */
    private MockObject $mockDomainProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpExtensionProducts();
        $this->setUpHostingProducts();
        $this->setUpSSLProducts();
        $this->setUpDnsProducts();

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->mockDomainProvider = $this->createMock(RtrService::class);

        $this->mockDomainProvider->method('setHandle')->willReturnSelf();

        $this->mockDomainProvider->method('setClient')->willReturnSelf();

        $this->app->bind(RtrService::class, fn () => $this->mockDomainProvider);

        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer);

        $this->validatorFactory = self::resolve(CartValidatorFactory::class);

        $productGroup = ProductGroup::where('slug', 'extension')->firstOrFail();
        $customer->productGroups()->save($productGroup, ['discount' => 42]);
    }

    #[DataProvider('successProvider')]
    #[Test]
    public function success(string $orderDataFilePath): void
    {
        if ($orderDataFilePath === __DIR__ . '/data/order.php') {
            $this->mockDomainProvider
                ->expects(self::once())
                ->method('check')
                ->with('ketchup.nl')
                ->willReturn(new CheckResult('ketchup.nl', 'free'));
        } else {
            $this->mockDomainProvider->expects(self::never())->method('check');
        }

        $orderData = include $orderDataFilePath;
        $validator = $this->validatorFactory->make($orderData, []);

        self::assertFalse($validator->fails());
    }

    /**
     * @return mixed[]
     */
    public static function successProvider(): array
    {
        return [
            [__DIR__ . '/data/order.php'],
            [__DIR__ . '/data/order_dns.php'],
        ];
    }

    #[DataProvider('failProvider')]
    #[Test]
    public function fails(string $orderDataFilePath): void
    {
        $this->mockDomainProvider
            ->expects(self::once())
            ->method('check')
            ->with('ketchup.nl')
            ->willReturn(new CheckResult('ketchup.nl', 'free'));

        $orderData = include $orderDataFilePath;
        $validator = $this->validatorFactory->make($orderData, []);
        self::assertTrue($validator->fails());
    }

    /**
     * @return mixed[]
     */
    public static function failProvider(): array
    {
        return [
            [__DIR__ . '/data/order_fail.php'],
        ];
    }

    private function setUpExtensionProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::EXTENSION,
            'slug' => ProductGroupType::EXTENSION,
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => '.nl',
            'slug' => 'extension_nl',
        ]);
    }

    private function setUpHostingProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        // Basic hosting
        new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'basic',
            'slug' => 'hosting_basic',
        ]);
    }

    private function setUpSSLProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::SSL,
            'slug' => ProductGroupType::SSL,
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'Extended Validation',
            'slug' => 'ssl_extended_validation',
        ]);
    }

    private function setUpDnsProducts(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::DNS,
            'slug' => ProductGroupType::DNS,
        ]);

        $basicDnsProduct = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => 'DNS',
            'slug' => ProductType::BASIC_DNS->value,
        ]);
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($basicDnsProduct)
            ->create();

        $legacyDnsProduct = new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => ProductType::FREE_DNS->value,
            'slug' => ProductType::FREE_DNS->value,
        ]);
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($legacyDnsProduct)
            ->create();
    }
}
