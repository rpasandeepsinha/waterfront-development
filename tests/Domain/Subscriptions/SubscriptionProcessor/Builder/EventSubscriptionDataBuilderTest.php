<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\SubscriptionProcessor\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsExternalNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainContactService;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Ssl\Factories\SslServiceFactory;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;

#[CoversClass(EventSubscriptionDataBuilder::class)]
class EventSubscriptionDataBuilderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ProviderFactory::new()->domainRtr()->createOne();
    }

    #[Test]
    public function buildDomainSubscriptionWithoutDnsChild(): void
    {
        $domain = 'create-domain-deployment.nl';
        $dnssec = false;
        $privateWhois = false;
        $transferSecret = null;

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->forDomain($domain)
            ->createOne();

        $mockDomainRepository = self::createMock(DomainDeploymentRepository::class);

        $mockLogger = self::createMock(LoggerInterface::class);

        $builder = new EventSubscriptionDataBuilder(
            domainServiceFactory: self::resolve(DomainServiceFactory::class),
            sslServiceFactory: self::createStub(SslServiceFactory::class),
            cartSerializerFactory: self::createStub(CartSerializerFactory::class),
            domainRepository: $mockDomainRepository,
            logger: $mockLogger,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            nameserverAssignerFactory: self::resolve(NameserverAssignerFactory::class),
            domainContactService: self::resolve(DomainContactService::class),
        );

        $mockDomainRepository
            ->expects(self::once())
            ->method('getDnsChildSubscription')
            ->with($domainSubscription)
            ->willReturn(null);

        $mockLogger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Created a DomainDeployment({provisioning.id}) for subscription {subscription.id} where there is no DNS child subscription.',
            );

        self::assertSame(0, DomainDeployment::query()->count());

        $builder->buildDomainDeployment($domainSubscription, $dnssec, $privateWhois, $transferSecret);

        $createdDomainDeployment = DomainDeployment::where('subscription_uuid', $domainSubscription->uuid)->first();

        self::assertSame(1, DomainDeployment::query()->count());
        self::assertNotNull($createdDomainDeployment);
    }

    #[Test]
    public function buildDomainSubscriptionWithNonPremiumDnsChild(): void
    {
        $domain = 'create-domain-deployment-with-dns-child.nl';
        $dnssec = false;
        $privateWhois = false;
        $transferSecret = null;

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->forDomain($domain)
            ->createOne();

        $dnsChildSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->freeDns())
            ->createOne(['parent_subscription_id' => $domainSubscription->id]);

        $mockDomainRepository = self::createMock(DomainDeploymentRepository::class);

        $mockLogger = self::createMock(LoggerInterface::class);

        $builder = new EventSubscriptionDataBuilder(
            domainServiceFactory: self::resolve(DomainServiceFactory::class),
            sslServiceFactory: self::createStub(SslServiceFactory::class),
            cartSerializerFactory: self::createStub(CartSerializerFactory::class),
            domainRepository: $mockDomainRepository,
            logger: $mockLogger,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            nameserverAssignerFactory: self::resolve(NameserverAssignerFactory::class),
            domainContactService: self::resolve(DomainContactService::class),
        );

        $mockDomainRepository
            ->expects(self::once())
            ->method('getDnsChildSubscription')
            ->with($domainSubscription)
            ->willReturn($dnsChildSubscription);

        $mockLogger->expects(self::never())->method('warning');

        self::assertSame(0, DomainDeployment::query()->count());

        $builder->buildDomainDeployment($domainSubscription, $dnssec, $privateWhois, $transferSecret);

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $domainSubscription->uuid,
            'dnssec_enabled' => $dnssec,
            'private_whois_enabled' => $privateWhois,
            'transfer_secret' => $transferSecret,
        ]);
    }

    #[Test]
    public function buildDomainSubscriptionWithPremiumDnsChild(): void
    {
        $domain = 'create-domain-deployment-with-dns-child.nl';
        $dnssec = true;
        $privateWhois = true;
        $transferSecret = 'secret';

        $domainSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(
                new ProductFactory()->for(new ProductGroupFactory()->extension()),
            )
            ->forDomain($domain)
            ->createOne();

        $dnsProduct = new ProductFactory()->premiumDns()->createOne();

        new ProductSpecFactory()->for($dnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $dnsChildSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($dnsProduct)
            ->createOne(['parent_subscription_id' => $domainSubscription->id]);

        $mockDomainRepository = self::createMock(DomainDeploymentRepository::class);

        $mockLogger = self::createMock(LoggerInterface::class);
        $mockNsAssigner = self::createMock(AssignNameserversToDomainAction::class);

        $builder = new EventSubscriptionDataBuilder(
            domainServiceFactory: self::resolve(DomainServiceFactory::class),
            sslServiceFactory: self::createStub(SslServiceFactory::class),
            cartSerializerFactory: self::createStub(CartSerializerFactory::class),
            domainRepository: $mockDomainRepository,
            logger: $mockLogger,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            nameserverAssignerFactory: self::resolve(NameserverAssignerFactory::class),
            domainContactService: self::resolve(DomainContactService::class),
        );

        $mockDomainRepository
            ->expects(self::once())
            ->method('getDnsChildSubscription')
            ->with($domainSubscription)
            ->willReturn($dnsChildSubscription);

        $mockLogger->expects(self::never())->method('warning');

        $mockNsAssigner->expects(self::never())->method('assign');

        self::assertSame(0, DomainDeployment::query()->count());

        $builder->buildDomainDeployment($domainSubscription, $dnssec, $privateWhois, $transferSecret);

        self::assertDatabaseHas('domain_deployments', [
            'subscription_uuid' => $domainSubscription->uuid,
            'dnssec_enabled' => $dnssec,
            'private_whois_enabled' => $privateWhois,
            'transfer_secret' => $transferSecret,
        ]);
    }

    #[Test]
    public function buildDnsDeploymentFreeDns(): void
    {
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->freeDns())
            ->createOne();

        $mockDnsVanityNameserverAssigner = self::createMock(DnsVanityNameserverAssigner::class);
        $mockDnsVanityNameserverAssigner->expects(self::never())->method('assign');

        $mockDnsExternalNameserverAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDnsExternalNameserverAssigner->expects(self::never())->method('assign');

        $mockDnsNameserverAssigner = self::createMock(DnsNameserverAssigner::class);
        $mockDnsNameserverAssigner->expects(self::once())->method('assign');

        $mockNameserverAssignerFactory = new NameserverAssignerFactory(
            dnsNameserverAssigner: $mockDnsNameserverAssigner,
            dnsVanityNameserverAssigner: $mockDnsVanityNameserverAssigner,
            dnsExternalNameserverAssigner: $mockDnsExternalNameserverAssigner,
        );

        $eventDataBuilder = new EventSubscriptionDataBuilder(
            domainServiceFactory: self::resolve(DomainServiceFactory::class),
            sslServiceFactory: self::createStub(SslServiceFactory::class),
            cartSerializerFactory: self::createStub(CartSerializerFactory::class),
            domainRepository: self::createStub(DomainDeploymentRepository::class),
            logger: self::createStub(LoggerInterface::class),
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            nameserverAssignerFactory: $mockNameserverAssignerFactory,
            domainContactService: self::resolve(DomainContactService::class),
        );

        $eventDataBuilder->buildDnsDeployment(
            subscription: $dnsSubscription,
        );

        $dnsDeployment = DnsDeployment::where('subscription_uuid', $dnsSubscription->uuid)->first();

        self::assertInstanceOf(DnsDeployment::class, $dnsDeployment);
        self::assertSame(NameserverType::INTERNAL, $dnsDeployment->nameserver_type);
    }

    #[Test]
    public function buildDnsDeploymentPremiumDns(): void
    {
        $dnsProduct = new ProductFactory()->premiumDns()->createOne();

        new ProductSpecFactory()->for($dnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);
        $dnsSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($dnsProduct)
            ->forDomain('testdomain.com')
            ->createOne();

        $mockDnsVanityNameserverAssigner = self::createMock(DnsVanityNameserverAssigner::class);
        $mockDnsVanityNameserverAssigner->expects(self::once())->method('assign');

        $mockDnsNameserverAssigner = self::createMock(DnsNameserverAssigner::class);
        $mockDnsNameserverAssigner->expects(self::never())->method('assign');

        $mockDnsExternalNameserverAssigner = self::createMock(DnsExternalNameserverAssigner::class);
        $mockDnsExternalNameserverAssigner->expects(self::never())->method('assign');

        $mockNameserverAssignerFactory = new NameserverAssignerFactory(
            dnsNameserverAssigner: $mockDnsNameserverAssigner,
            dnsVanityNameserverAssigner: $mockDnsVanityNameserverAssigner,
            dnsExternalNameserverAssigner: $mockDnsExternalNameserverAssigner,
        );

        $eventDataBuilder = new EventSubscriptionDataBuilder(
            domainServiceFactory: self::resolve(DomainServiceFactory::class),
            sslServiceFactory: self::createStub(SslServiceFactory::class),
            cartSerializerFactory: self::createStub(CartSerializerFactory::class),
            domainRepository: self::createStub(DomainDeploymentRepository::class),
            logger: self::createStub(LoggerInterface::class),
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            nameserverAssignerFactory: $mockNameserverAssignerFactory,
            domainContactService: self::resolve(DomainContactService::class),
        );

        $eventDataBuilder->buildDnsDeployment(
            subscription: $dnsSubscription,
        );

        $dnsDeployment = DnsDeployment::where('subscription_uuid', $dnsSubscription->uuid)->first();
        self::assertInstanceOf(DnsDeployment::class, $dnsDeployment);
        self::assertSame(NameserverType::VANITY, $dnsDeployment->nameserver_type);
    }
}
