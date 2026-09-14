<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Validators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Cart\Validators\CartValidatorFactory;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(CartValidatorFactory::class)]
class DomainExtensionOrderValidationTest extends IntegrationTestCase
{
    private CartValidatorFactory $validatorFactory;

    private DomainContact $domainContact;

    protected function setUp(): void
    {
        parent::setUp();
        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer);

        $rtrMock = $this->createStub(RtrService::class);

        $this->app->bind(RtrService::class, fn () => $rtrMock);
        $this->validatorFactory = self::resolve(CartValidatorFactory::class);

        $customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($customer);

        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);
        $this->domainContact = new DomainContactFactory()->for($customer)->createOne();

        $this->app->bind(RtrService::class, fn () => $rtrMock);
        $this->setUpFreeDnsProduct();
        $this->setUpDomainExtensionProducts();
    }

    #[Test]
    public function domainChildValidation(): void
    {
        $orderDomainWithFreeDnsChild = include __DIR__ . '/data/order_domain_with_child.php';
        $orderDomainWithFreeDnsChild['subscriptions']['extension'][0]['contact_id'] = $this->domainContact->id;
        $validator = $this->validatorFactory->make($orderDomainWithFreeDnsChild, []);
        self::assertFalse($validator->fails());
    }

    #[Test]
    public function domainWithoutChildValidation(): void
    {
        $orderDomainWithoutFreeDnsChild = include __DIR__ . '/data/order_domain_without_child.php';
        $validator = $this->validatorFactory->make($orderDomainWithoutFreeDnsChild, []);
        self::assertTrue($validator->fails());
        self::assertArrayHasKey('subscriptions.extension.0.children', $validator->messages()->toArray());
    }

    #[Test]
    public function domainChildShouldBeFreeDnsValidation(): void
    {
        $orderDomainWithInvalidFreeDnsChild = include __DIR__ . '/data/order_domain_with_invalid_child.php';
        $validator = $this->validatorFactory->make($orderDomainWithInvalidFreeDnsChild, []);
        self::assertTrue($validator->fails());
        self::assertArrayHasKey('subscriptions.extension.0.children', $validator->messages()->toArray());
    }

    #[Test]
    public function domainChildShouldOnlyBeFreeDnsValidation(): void
    {
        $orderDomainWithInvalidFreeDnsChild = include __DIR__ . '/data/order_domain_with_valid_and_invalid_child.php';
        $validator = $this->validatorFactory->make($orderDomainWithInvalidFreeDnsChild, []);
        self::assertTrue($validator->fails());
        self::assertArrayHasKey('subscriptions.extension.0.children', $validator->messages()->toArray());
        self::assertArrayHasKey('subscriptions.extension.0.children.extension', $validator->messages()->toArray());
    }

    private function setUpFreeDnsProduct(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::DNS,
            'slug' => ProductGroupType::DNS,
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $productGroup->id,
            'name' => ProductType::FREE_DNS,
            'slug' => ProductType::FREE_DNS->value,
        ]);
    }

    private function setUpDomainExtensionProducts(): void
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
}
