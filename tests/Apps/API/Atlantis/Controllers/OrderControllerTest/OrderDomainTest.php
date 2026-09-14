<?php

declare(strict_types=1);

namespace Tests\Apps\API\Atlantis\Controllers\OrderControllerTest;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(OrderController::class)]
class OrderDomainTest extends IntegrationTestCase
{
    private const string DOMAIN = 'domain-order-test.nl';

    #[Test]
    public function orderDomain(): void
    {
        $customer = new CustomerFactory()->withAddress()->createOne();
        ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'enabled' => true,
            'default' => true,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);
        $domainContact = DomainContactFactory::new()->for($customer)->createOne();

        Event::fake(
            [
                CreateDns::class,
                CreateDomain::class,
            ],
        );

        $rtrMock = self::mock(RtrService::class);

        $rtrMock->shouldReceive('check')->with(self::DOMAIN)->andReturn(new CheckResult(self::DOMAIN, 'free'));

        $rtrMock->shouldReceive('setHandle')->andReturnSelf();

        $rtrMock->shouldReceive('setClient')->andReturnSelf();

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        $domainProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->for($domainProduct)
            ->registration()
            ->createOne(['price' => 499]);

        $freeDnsProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->dns())
            ->freeDns()
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($freeDnsProduct)
            ->registration()
            ->createOne(['price' => 0]);

        $json = (string) file_get_contents(__DIR__ . '/data/order_payload_domain.json');

        /** @var array<int, array<string>> $orderPayload */
        $orderPayload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $orderPayload['subscriptions']['extension'][0]['contact_id'] = $domainContact->id;

        self::assertDatabaseEmpty('subscriptions');

        $response = $this->actingAsCustomer($customer)->postJson(
            $this->generateRoute('partners.order.order'),
            $orderPayload,
        );

        self::assertCount(2, Subscription::all());
        $domainSubscription = Subscription::whereProductSlug($domainProduct->slug)
            ->where('domain', self::DOMAIN)
            ->firstOrFail();
        $dnsSubscription = Subscription::whereProductSlug($freeDnsProduct->slug)
            ->where('domain', self::DOMAIN)
            ->firstOrFail();

        self::assertCount(1, $domainSubscription->children);
        self::assertSame($dnsSubscription->id, $domainSubscription->children->firstOrFail()->id);
        self::assertSame($domainSubscription->id, $dnsSubscription->parent_subscription_id);

        self::assertSame(AdministrativeStatus::ACTIVE->value, $domainSubscription->administrative_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $dnsSubscription->administrative_status);

        $response->assertOk();

        Event::assertDispatched(CreateDns::class);
        Event::assertDispatched(CreateDomain::class);
    }
}
