<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Integration;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductAllowedChangeFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\ChangeDnsAction;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Services\PowerDnsNameserverSynchronizer;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(ChangeDnsAction::class)]
class DnsDowngradeOnTerminationTest extends IntegrationTestCase
{
    private const string DOMAIN = 'dns-downgrade-on-termination.test';

    #[Test]
    public function successfulDowngradeOnTermination(): void
    {
        $domainDetailFile = include __DIR__ . '/data/domainDetailsValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetailFile, DomainDetailsDTO::class);

        $customer = new CustomerFactory()->createOne();

        $dnsProductGroup = new ProductGroupFactory()->dns()->createOne();
        $freeDnsProduct = new ProductFactory()
            ->freeDns($dnsProductGroup)
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($freeDnsProduct)
            ->prolongation()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);

        $premiumDnsProduct = new ProductFactory()
            ->premiumDns($dnsProductGroup)
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($premiumDnsProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 2388,
            ]);
        new ProductSpecFactory()
            ->for($premiumDnsProduct)
            ->for($premiumDnsProduct)
            ->createOne([
                'name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED,
                'value' => $freeDnsProduct->slug,
            ]);

        $nlDomain = new ProductFactory()->nlDomain()->createOne();

        $parentDomainSubscription = new SubscriptionFactory()
            ->forDomain(self::DOMAIN)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->for($customer)
            ->for($nlDomain)
            ->has(new DomainDeploymentFactory()->withRtrProvider())
            ->createOne();

        $premiumDnsSubscription = new SubscriptionFactory()
            ->forDomain(self::DOMAIN)
            ->administrativeStatusExpired()
            ->technicalStatusOk()
            ->for($customer)
            ->for($premiumDnsProduct)
            ->has(new DnsDeploymentFactory())
            ->createOne([
                'parent_subscription_id' => $parentDomainSubscription->id,
                'start_date' => CarbonImmutable::now()->subYear(),
                'end_date' => CarbonImmutable::now()->subDay(),
                'termination_date' => CarbonImmutable::now()->subDay(),
                'net_price' => 2388,
            ]);

        ProductAllowedChangeFactory::new()->downgradeChange()->create([
            'from_product_id' => $premiumDnsProduct->id,
            'to_product_id' => $freeDnsProduct->id,
        ]);

        $mockPdns = self::mock(PowerDnsClient::class);
        $this->app->bind(PowerDnsClient::class, fn () => $mockPdns);

        $dnsZone = new DnsZone(new Fqdn(self::DOMAIN));

        $mockPdns->shouldReceive('getZone')->with(self::DOMAIN)->andReturn($dnsZone);

        $mockPdns->shouldReceive('deleteMetadata')->with(self::DOMAIN, PowerDnsMetadataType::ALLOW_AXFR_FROM);

        $mockPdns->shouldReceive('deleteMetadata')->with(self::DOMAIN, PowerDnsMetadataType::ALSO_NOTIFY);

        $mockPdns->shouldReceive('deleteMetadata')->with(self::DOMAIN, PowerDnsMetadataType::SOA_EDIT);

        $mockPdns->shouldReceive('updateLiveDns')->with(self::DOMAIN, false);

        $mockPdns->shouldReceive('changeZone')->andReturn($dnsZone);

        $mockGandiClient = self::mock(GandiClient::class);
        $this->app->bind(GandiClient::class, fn () => $mockGandiClient);

        $mockGandiClient->shouldReceive('deleteDomain')->with(self::DOMAIN);

        $rtrMock = $this->createMock(RtrService::class);

        $this->app->bind(RtrService::class, fn () => $rtrMock);

        $rtrMock->expects(self::exactly(1))->method('modify')->willReturn(true);

        $rtrMock->expects(self::exactly(2))->method('fetchDomain')->with(self::DOMAIN)->willReturn($domainDetails);

        $rtrMock->method('getPrimaryDomainStatusFromDomainStatusList')->willReturn(RtrDomainStatus::OK);

        $rtrMock->method('setHandle')->willReturnSelf();

        $rtrMock->method('setClient')->willReturnSelf();

        $mockPdnsSync = self::mock(PowerDnsNameserverSynchronizer::class);
        $this->app->bind(PowerDnsNameserverSynchronizer::class, fn () => $mockPdnsSync);

        $mockPdnsSync->shouldReceive('synchronize')->with(self::DOMAIN, self::anything(), false);

        $terminateCommand = self::artisan('subscriptions:terminate');
        $terminateCommand->execute();

        $parentDomainSubscription->refresh();
        $premiumDnsSubscription->refresh();

        self::assertSame(DomainStatus::ACTIVE->value, $parentDomainSubscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $parentDomainSubscription->administrative_status);

        self::assertNull($premiumDnsSubscription->termination_date);

        self::assertSame(TechnicalStatus::OK->value, $premiumDnsSubscription->technical_status);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $premiumDnsSubscription->administrative_status);
        self::assertSame($freeDnsProduct->slug, $premiumDnsSubscription->product->slug);
    }
}
