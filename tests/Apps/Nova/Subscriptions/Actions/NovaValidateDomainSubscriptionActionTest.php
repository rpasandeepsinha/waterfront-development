<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Subscriptions\Actions;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Http\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaValidateDomainSubscriptionAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Serializers\DomainSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaValidateDomainSubscriptionAction::class)]
#[AllowMockObjectsWithoutExpectations]
class NovaValidateDomainSubscriptionActionTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave-test.com';

    private Subscription $domainSubscription;

    private DomainDeployment $domainDeployment;

    private Subscription $dnsSubscription;

    private DomainDetailsDTO $domainDetails;

    private DomainService&MockObject $mockDomainService;

    private DnsService&MockObject $mockDnsService;

    private DnsDeploymentRepository&MockObject $mockDnsDeploymentRepository;

    private GandiClient&MockObject $mockGandiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

        $this->domainSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        $this->domainDeployment = DomainDeploymentFactory::new()
            ->withRtrProvider()
            ->for($this->domainSubscription, 'subscription')
            ->createOne();

        $dnsGroup = new ProductGroupFactory()->dns()->createOne();
        $dnsProduct = new ProductFactory()
            ->premiumDns($dnsGroup)
            ->createOne();

        $this->dnsSubscription = new SubscriptionFactory()
            ->for($customer)
            ->for($dnsProduct)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->forDomain(self::DOMAIN)
            ->createOne();

        DnsDeploymentFactory::new()->for($this->dnsSubscription)->createOne();

        new ProductSpecFactory()->for($dnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $domainDetails = include __DIR__ . '/data/domainDetailsValid.php';
        $serializer = DomainSerializerFactory::getSerializer();
        $domainDetails = $serializer->denormalize($domainDetails, DomainDetailsDTO::class);
        $this->domainDetails = $domainDetails;

        $this->mockDomainService = self::createMock(DomainService::class);
        $this->mockDnsService = self::createMock(DnsService::class);
        $this->mockDnsDeploymentRepository = self::createMock(DnsDeploymentRepository::class);
        $this->mockGandiClient = self::createMock(GandiClient::class);
    }

    #[Test]
    public function noDomain(): void
    {
        $this->domainSubscription->domain = null;
        $this->domainSubscription->save();

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>❌ nova-action.validate-dns-domain.no-domainnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function wrongGroupExtension(): void
    {
        $this->domainSubscription->product->productGroup->slug = ProductGroupType::HOSTING;
        $this->domainSubscription->save();

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>❌ nova-action.validate-dns-domain.no-product-group-extensionnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function noDomainDeployment(): void
    {
        $this->domainDeployment->delete();

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>❌ nova-action.validate-dns-domain.no-domain-deploymentnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function fetchDomainError(): void
    {
        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->willThrowException(new FetchDomainException());

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>❌ nova-action.validate-dns-domain.domain-could-not-be-retrievednova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function noDnsSubscription(): void
    {
        $this->dnsSubscription->domain = 'invalid.com';
        $this->dnsSubscription->save();

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>❌ nova-action.validate-dns-domain.no-dns-subscriptionnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function dnsDeploymentNoNameserverAndNoDomainNameserver(): void
    {
        $this->domainDetails->ns = [];

        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->willThrowException(new FailedToFetchNameserversException(self::DOMAIN));

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>✅ nova-action.validate-dns-domain.has-domain</li><li>❌ nova-action.validate-dns-domain.dns-deployment-no-nameserver</li><li>❌ nova-action.validate-dns-domain.no-domain-nameservers-registry</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-registry</li><li>❌ nova-action.validate-dns-domain.no-domain-nameservers-dns-server</li><li>❌ nova-action.validate-dns-domain.no-nameserversnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function noDnsZone(): void
    {
        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $this->mockDnsService
            ->expects(self::once())
            ->method('getDnsZone')
            ->willThrowException(new DnsZoneNotFoundException());

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.dns-subscription-nameservers</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-registry</li><li>❌ nova-action.validate-dns-domain.no-dns-zonenova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function noDomainNameServers(): void
    {
        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->addRecord(
            new DefaultRecord(
                'TXT',
                'test',
                'ns1.sandwave-test.com.',
                3600,
                disabled: false,
            ),
        );
        $zone->addRecord(
            new DefaultRecord(
                'TXT',
                'test',
                'ns2.sandwave-test.com.',
                3600,
                disabled: false,
            ),
        );

        $this->mockDnsService->expects(self::once())->method('getDnsZone')->willReturn($zone);

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.dns-subscription-nameservers</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-registry</li><li>❌ nova-action.validate-dns-domain.no-domain-nameservers-dns-server</li><li>❌ nova-action.validate-dns-domain.no-nameserversnova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function noGandiZone(): void
    {
        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $gandiFakeResponse = json_encode([
            'message' => 'Zone not found',
            'status' => 404,
        ]);

        $mockSaloonResponse = self::createMock(Response::class);

        $mockSaloonResponse->expects(self::once())->method('body')->willReturn($gandiFakeResponse);

        $this->mockGandiClient
            ->expects(self::once())
            ->method('getDnsRecords')
            ->with(self::DOMAIN)
            ->willThrowException(new NotFoundException($mockSaloonResponse));

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.dns-subscription-nameservers</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-registry</li><li>❌ nova-action.validate-dns-domain.gandi-no-dns-zonenova-action.validate-dns-domain.further-validation</li></ul>',
            $modal->payload['html'],
        );
    }

    #[Test]
    public function fullyWorkingAction(): void
    {
        $this->mockDomainService
            ->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN, ProviderSlug::REALTIME_REGISTER)
            ->willReturn($this->domainDetails);

        $zone = new DnsZone(new Fqdn(self::DOMAIN));
        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'test',
                'ns1.sandwave-test.com.',
                3600,
                disabled: false,
            ),
        );
        $zone->addRecord(
            new DefaultRecord(
                'NS',
                'test',
                'ns2.sandwave-test.com.',
                3600,
                disabled: false,
            ),
        );
        $this->mockDnsService->expects(self::once())->method('getDnsZone')->willReturn($zone);

        $expectedVanityArray = [
            new Nameserver('ns1.sandwave-test.com'),
            new Nameserver('ns2.sandwave-test.com'),
        ];

        $this->mockDnsDeploymentRepository
            ->expects(self::once())
            ->method('getNameservers')
            ->with($this->dnsSubscription->dnsDeployment)
            ->willReturn($expectedVanityArray);

        $gandiFakeResponse = [
            'rrset_name' => self::DOMAIN,
            'rrset_type' => 'NS',
            'rrset_ttl' => 10800,
            'rrset_values' => [
                'ns1.sandwave-test.com',
                'ns2.sandwave-test.com',
            ],
        ];

        $this->mockGandiClient
            ->expects(self::once())
            ->method('getDnsRecords')
            ->with(self::DOMAIN)
            ->willReturn($gandiFakeResponse);

        $result = $this->runAction();

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertSame(
            '<ul><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.is-product-group-extension</li><li>✅ nova-action.validate-dns-domain.has-domain-deployment</li><li>✅ nova-action.validate-dns-domain.domain-could-be-retrieved</li><li>✅ nova-action.validate-dns-domain.has-domain</li><li>✅ nova-action.validate-dns-domain.dns-subscription-nameservers</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-registry</li><li>✅ nova-action.validate-dns-domain.gandi-dns-zone-retrieved</li><li>✅ nova-action.validate-dns-domain.domain-nameservers-dns-server</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li><li>✅ nova-action.validate-dns-domain.nameserver-has-at</li></ul>',
            $modal->payload['html'],
        );
    }

    private function runAction(): ActionResponse|NovaValidateDomainSubscriptionAction
    {
        $action = new NovaValidateDomainSubscriptionAction(
            translator: self::resolve(TranslatorInterface::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            domainService: $this->mockDomainService,
            dnsDeploymentRepository: $this->mockDnsDeploymentRepository,
            dnsService: $this->mockDnsService,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            gandiClient: $this->mockGandiClient,
        );

        $subscriptions = new Collection([$this->domainSubscription]);

        return $action->handle(
            new ActionFields(new Collection(), new Collection()),
            $subscriptions,
        );
    }
}
