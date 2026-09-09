<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Serializers;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Orders\DTO\CartOrder;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductPriceType;

/**
 * Normally we wouldn't test these DTO's so explicitly, they
 * would be covered in the unit tests of classes that use
 * these DTOs to transfer data from other classes here.
 *
 * But in the current state we do not have a complete test coverage
 * of the orders. This is a core feature of our application, so we
 * test our DTO's here to ensure order processing from Atlantis.
 */
#[CoversClass(CartOrder::class)]
class CartSerializerFactoryTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new CartSerializerFactory()->get();
    }

    #[Test]
    public function domainHostingSslDeserialize(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/domain-hosting-ssl-cart.json');
        $cartOrder = $this->serializer->deserialize($cartJson, CartOrder::class, 'json');

        self::assertIsArray($cartOrder->subscriptions->extension);
        self::assertCount(1, $cartOrder->subscriptions->extension);

        $extensionOrder = $cartOrder->subscriptions->extension[0];

        self::assertSame(ProductPriceType::REGISTRATION, $extensionOrder->status);
        self::assertSame('cart-to-order.nl', $extensionOrder->domain);
        self::assertSame(12, $extensionOrder->billingPeriod);
        self::assertSame(12, $extensionOrder->contractPeriod);
        self::assertSame('extension_nl', $extensionOrder->slug);

        self::assertIsArray($cartOrder->subscriptions->hosting);
        self::assertCount(1, $cartOrder->subscriptions->hosting);

        $hostingOrder = $cartOrder->subscriptions->hosting[0];

        self::assertSame(ProductPriceType::REGISTRATION, $hostingOrder->status);
        self::assertSame('cart-to-order.nl', $hostingOrder->domain);
        self::assertSame(12, $hostingOrder->billingPeriod);
        self::assertSame(12, $hostingOrder->contractPeriod);
        self::assertSame('hosting_basic', $hostingOrder->slug);

        self::assertIsArray($cartOrder->subscriptions->ssl);
        self::assertCount(1, $cartOrder->subscriptions->ssl);

        $sslOrder = $cartOrder->subscriptions->ssl[0];

        self::assertSame(ProductPriceType::REGISTRATION, $sslOrder->status);
        self::assertSame('cart-to-order.nl', $sslOrder->domain);
        self::assertSame(12, $sslOrder->billingPeriod);
        self::assertSame(12, $sslOrder->contractPeriod);
        self::assertSame('ssl_single_domain', $sslOrder->slug);
    }

    #[Test]
    public function cloudstackVirtualMachineWithChild(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/vps-cart.json');
        $cartOrder = $this->serializer->deserialize($cartJson, CartOrder::class, 'json');

        self::assertIsArray($cartOrder->subscriptions->vps);
        self::assertCount(1, $cartOrder->subscriptions->vps);

        $vpsOrder = $cartOrder->subscriptions->vps[0];

        self::assertSame(ProductPriceType::REGISTRATION, $vpsOrder->status);
        self::assertNull($vpsOrder->domain);
        self::assertSame(12, $vpsOrder->billingPeriod);
        self::assertSame(12, $vpsOrder->contractPeriod);
        self::assertSame('vps-32-red', $vpsOrder->slug);

        self::assertNotNull($vpsOrder->children);
        self::assertIsArray($vpsOrder->children->vpsOs);
        self::assertCount(1, $vpsOrder->children->vpsOs);

        $vpsChildOrder = $vpsOrder->children->vpsOs[0];

        self::assertSame(ProductPriceType::REGISTRATION, $vpsChildOrder->status);
        self::assertSame(1, $vpsChildOrder->billingPeriod);
        self::assertSame(1, $vpsChildOrder->contractPeriod);
        self::assertNull($vpsChildOrder->domain);
        self::assertSame('ubuntu-lts-20.04', $vpsChildOrder->slug);
    }

    #[Test]
    public function microsoft365Deserialize(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/m365-cart.json');
        $cartOrder = $this->serializer->deserialize($cartJson, CartOrder::class, 'json');

        self::assertIsArray($cartOrder->subscriptions->microsoft365);
        self::assertCount(2, $cartOrder->subscriptions->microsoft365);

        foreach ($cartOrder->subscriptions->microsoft365 as $m365Order) {
            self::assertSame(ProductPriceType::REGISTRATION, $m365Order->status);
            self::assertSame(1, $m365Order->billingPeriod);
            self::assertSame(1, $m365Order->contractPeriod);
            self::assertSame('microsoft-business-standard', $m365Order->slug);
        }
    }

    #[Test]
    public function addOnDeserialization(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/addon-cart.json');
        $cartOrder = $this->serializer->deserialize($cartJson, CartOrder::class, 'json');

        self::assertIsArray($cartOrder->subscriptions->addOn);
        self::assertCount(1, $cartOrder->subscriptions->addOn);

        $addOnOrder = $cartOrder->subscriptions->addOn[0];

        self::assertSame(ProductPriceType::REGISTRATION, $addOnOrder->status);
        self::assertSame('19f00910-3b70-11ee-87b9-024265528047', $addOnOrder->parentSubscriptionUuid);
        self::assertNull($addOnOrder->domain);
        self::assertSame(12, $addOnOrder->billingPeriod);
        self::assertSame(12, $addOnOrder->contractPeriod);
        self::assertSame('fancy_installer', $addOnOrder->slug);
    }

    #[Test]
    public function cartOrderDeserialization(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/domain-hosting-ssl-cart.json');
        $cartOrder = $this->serializer->deserialize($cartJson, CartOrder::class, 'json');

        self::assertSame('ideal', $cartOrder->paymentMethod);
    }

    #[Test]
    public function fromStoreRequest(): void
    {
        $cartJson = (string) file_get_contents(__DIR__ . '/data/domain-hosting-ssl-cart.json');
        $requestContent = json_decode($cartJson, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($requestContent));

        $request = Request::create('partners.order.order', 'POST', $requestContent);

        $cartOrder = $this->serializer->denormalize($request->all(), CartOrder::class);
        self::assertIsArray($cartOrder->subscriptions->extension);
        self::assertCount(1, $cartOrder->subscriptions->extension);

        $extensionOrder = $cartOrder->subscriptions->extension[0];

        self::assertSame(ProductPriceType::REGISTRATION, $extensionOrder->status);
        self::assertSame('cart-to-order.nl', $extensionOrder->domain);
        self::assertSame(12, $extensionOrder->billingPeriod);
        self::assertSame(12, $extensionOrder->contractPeriod);
        self::assertSame('extension_nl', $extensionOrder->slug);

        self::assertIsArray($cartOrder->subscriptions->hosting);
        self::assertCount(1, $cartOrder->subscriptions->hosting);

        $hostingOrder = $cartOrder->subscriptions->hosting[0];

        self::assertSame(ProductPriceType::REGISTRATION, $hostingOrder->status);
        self::assertSame('cart-to-order.nl', $hostingOrder->domain);
        self::assertSame(12, $hostingOrder->billingPeriod);
        self::assertSame(12, $hostingOrder->contractPeriod);
        self::assertSame('hosting_basic', $hostingOrder->slug);

        self::assertIsArray($cartOrder->subscriptions->ssl);
        self::assertCount(1, $cartOrder->subscriptions->ssl);

        $sslOrder = $cartOrder->subscriptions->ssl[0];

        self::assertSame(ProductPriceType::REGISTRATION, $sslOrder->status);
        self::assertSame('cart-to-order.nl', $sslOrder->domain);
        self::assertSame(12, $sslOrder->billingPeriod);
        self::assertSame(12, $sslOrder->contractPeriod);
        self::assertSame('ssl_single_domain', $sslOrder->slug);
    }
}
