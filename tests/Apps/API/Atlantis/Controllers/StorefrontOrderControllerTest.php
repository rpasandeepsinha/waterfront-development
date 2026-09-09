<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

#[CoversClass(OrderController::class)]
class StorefrontOrderControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $dnsProduct = new ProductFactory()->freeDns()->createOne();
        ProductSpecFactory::new()
            ->enable(ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT)
            ->for($dnsProduct)
            ->create();
        new ProductPriceComponentFactory()->for($dnsProduct)->registration()->createOne(['price' => 0]);

        new ServerFactory()->createOne();
        $domainProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
        ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::PLESK, 'enabled' => true, 'default' => true]);
        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);

        $extensionGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);
        $hostingGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
            'name' => ProductGroupType::HOSTING,
        ]);
        $sslGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::SSL,
            'name' => ProductGroupType::SSL,
        ]);

        $sslProduct = new ProductFactory()->for($sslGroup)->createOne([
            'name' => 'Single Domain',
            'slug' => 'ssl_single_domain',
        ]);
        new ProductSpecFactory()->for($sslProduct)->createOne(['name' => 'ssl.product_id', 'value' => $sslProduct->id]);
        new ProductPriceComponentFactory()->for($sslProduct)->registration()->createOne([
            'price' => 120,
        ]);
        new ProductPriceComponentFactory()->for($sslProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);
        $comProduct = new ProductFactory()->for($extensionGroup)->createOne([
            'name' => '.com',
            'slug' => 'extension_com',
        ]);

        new ProductSpecFactory()->for($comProduct)->createOne(
            ['name' => 'domain.provider_id', 'value' => $domainProvider->id]
        );
        new ProductPriceComponentFactory()->for($comProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($comProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);

        $hostingProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'name' => 'premium',
            'slug' => 'hosting_premium',
        ]);

        new ProductPriceComponentFactory()->for($hostingProduct)->registration()->createOne(['price' => 120]);
        new ProductPriceComponentFactory()->for($hostingProduct)->createOne(['type' => PriceComponentType::PROMOTION, 'price' => 96]);
    }

    #[Test]
    public function orderSuccess(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');
        $customer = new CustomerFactory()->withAddress()->createOne();

        $this->app->bind(Dispatcher::class, fn () => self::createStub(Dispatcher::class));
        $response = (string) $this
            ->actingAsCustomer($customer)
            ->json(
                'post',
                $this->generateRoute('partners.order.order'),
                (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR)
            )->getContent();

        /** @var array<mixed> $responseData */
        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        self::assertDatabaseHas('orders', [
            'uuid' => $responseData['transactionId'],
        ]);

        self::assertCount(4, (array) $responseData['data']);
        self::assertSame(TechnicalStatus::OK->value, $responseData['status']);
        self::assertNull($responseData['checkout_url']);
    }

    #[Test]
    public function orderSuccessNeedsPayment(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');
        $customer = new CustomerFactory()->withAddress()->createOne(['payment_type' => PaymentType::DIRECT]);

        $response = (string) $this
            ->actingAsCustomer($customer, verified: false)
            ->json(
                'post',
                $this->generateRoute('partners.order.order'),
                (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR)
            )->getContent();

        /** @var array<mixed> $responseData */
        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        self::assertDatabaseHas('orders', [
            'uuid' => $responseData['transactionId'],
        ]);

        self::assertCount(4, (array) $responseData['data']);
        self::assertSame('needsPayment', $responseData['status']);
        self::assertNotEmpty($responseData['checkout_url']);
    }

    #[Test]
    public function createDefaultOwnerWithoutAddressFails(): void
    {
        $mockDnsService = self::mock(DnsService::class);
        $dnsZone = new DnsZone(new Fqdn('test.com'));
        $this->app->bind(DnsService::class, fn (): DnsService => $mockDnsService);
        $mockDnsService->shouldReceive('hasDnsZone')
            ->andReturnTrue();

        $mockDnsService->shouldReceive('getDnsZone')
            ->with('test.com')
            ->andReturn($dnsZone);

        $mockDnsService->shouldReceive('applyDiffToZone')
            ->andReturn($dnsZone);

        $mockDomainService = self::mock(DomainService::class);
        $this->app->bind(DomainService::class, fn (): DomainService => $mockDomainService);

        $mockDomainService->shouldReceive('register')
            ->andReturn(new RegistrationResult(DomainStatus::ACTIVE));

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload.json');
        $customer = new CustomerFactory()->createOne();

        $response = (string) $this
            ->actingAsCustomer($customer)
            ->json(
                'post',
                $this->generateRoute('partners.order.order'),
                (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR)
            )->getContent();

        /** @var array<mixed> $result */
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Cannot create default owner for customer without address.', $result['message']);
    }
}
