<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(SubscriptionRepository::class)]
class SubscriptionRepositoryProductExistsTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $group = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::HOSTING, 'name' => 'Hosting']);
        $product = new ProductFactory()->createOne(['product_group_id' => $group->id]);

        // Customer without subscription
        new CustomerFactory()->createOne(['id' => 1]);

        $regularPrice = 100;

        // Customer and its subscription
        $customerWithSubscription = new CustomerFactory()->createOne(['id' => 2]);
        Subscription::create([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'product_uuid' => $product->uuid,
            'contract_period' => 12,
            'billing_period' => 12,
            'customer_id' => $customerWithSubscription->id,
            'domain' => 'customerWithDomain.nl',
            'gross_price' => $regularPrice,
            'net_price' => $regularPrice,
        ]);
        Subscription::create([
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'product_uuid' => $product->uuid,
            'contract_period' => 12,
            'billing_period' => 12,
            'customer_id' => $customerWithSubscription->id,
            'domain' => 'customerWithSecondDomain.nl',
            'gross_price' => $regularPrice,
            'net_price' => $regularPrice,
        ]);
        Subscription::create([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            'product_uuid' => $product->uuid,
            'contract_period' => 12,
            'billing_period' => 12,
            'customer_id' => $customerWithSubscription->id,
            'domain' => 'customerWithThirdDomain.nl',
            'gross_price' => $regularPrice,
            'net_price' => $regularPrice,
        ]);
        Subscription::create([
            'administrative_status' => 'randomStatus',
            'product_uuid' => $product->uuid,
            'contract_period' => 12,
            'billing_period' => 12,
            'customer_id' => $customerWithSubscription->id,
            'domain' => 'customerWithFourthDomain.nl',
            'gross_price' => $regularPrice,
            'net_price' => $regularPrice,
        ]);
    }

    #[DataProvider('productExistsData')]
    #[Test]
    public function productExists(
        ProductGroupType $slug,
        string $domain,
        ?int $customerId,
        bool $expectation,
        string $message,
    ): void {
        $repository = new SubscriptionRepository(
            self::createStub(PriceResolver::class),
            self::createStub(PricePersistService::class),
            self::createStub(ConfigurationInterface::class),
            self::createStub(StoreNoteAction::class),
        );
        $result = $repository->productExists($slug, $domain, $customerId);
        self::assertSame($expectation, $result, $message);
    }

    /**
     * Test data used by the ProductExists method on the SubscriptionRepository.
     *
     * @return array<mixed>
     */
    public static function productExistsData(): array
    {
        return [
            [
                ProductGroupType::HOSTING,
                'customerWithDomain.nl',
                2,
                true,
                'product of group exists on domain for customer with product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithDomain.nl',
                1,
                false,
                'product of group does not exist domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithDomain.nl',
                null,
                true,
                'product of group exists on domain for any customer',
            ],
            [
                ProductGroupType::HOSTING,
                '_testdomain123.nl',
                2,
                false,
                'product of group does not exists on domain for customer with product',
            ],
            [
                ProductGroupType::HOSTING,
                '_testdomain123.nl',
                1,
                false,
                'product of group does not exists on domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                '_testdomain123.nl',
                null,
                false,
                'product of group does not exists on domain for any customer',
            ],
            [
                ProductGroupType::DNS,
                'customerWithDomain.nl',
                2,
                false,
                'product of group does not exists on domain for customer',
            ],
            [
                ProductGroupType::DNS,
                'customerWithDomain.nl',
                1,
                false,
                'product of group does not exists on domain for customer',
            ],
            [ProductGroupType::DNS, '_testdomain123.nl', 2, false, 'product of group does not exists for any customer'],
            [ProductGroupType::DNS, '_testdomain123.nl', 1, false, 'product of group does not exists for any customer'],
            [
                ProductGroupType::DNS,
                'customerWithDomain.nl',
                null,
                false,
                'product of group does not exists on domain for customer',
            ],
            [
                ProductGroupType::DNS,
                '_testdomain123.nl',
                null,
                false,
                'product of group does not exists for any customer',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithSecondDomain.nl',
                1,
                false,
                'cancelled product of group does not exist domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithSecondDomain.nl',
                2,
                true,
                'cancelled product of group does exist on domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithSecondDomain.nl',
                null,
                true,
                'cancelled product of group exists on domain for any customer',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithThirdDomain.nl',
                1,
                false,
                'deleted product of group does not exist domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithThirdDomain.nl',
                2,
                false,
                'deleted product of group does exist on domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithThirdDomain.nl',
                null,
                false,
                'deleted product of group exists on domain for any customer',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithFourthDomain.nl',
                1,
                false,
                'random status product of group does not exist domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithFourthDomain.nl',
                2,
                true,
                'random status product of group does exist on domain for customer without product',
            ],
            [
                ProductGroupType::HOSTING,
                'customerWithFourthDomain.nl',
                null,
                true,
                'random status product of group exists on domain for any customer',
            ],
        ];
    }
}
