<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use RealtimeRegister\Domain\ProcessCollection;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\DomainController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(DomainController::class)]
class DomainControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showDomainContactForDomain(): void
    {
        $domainContact = new DomainContactFactory()->for(new CustomerFactory()->createOne())->createOne();

        $provider = new ProviderFactory()->domainOpenProvider()->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->createOne())
            ->for(new ProductFactory()->nlDomain())
            ->createOne(['domain' => self::DOMAIN]);
        new DomainDeploymentFactory()
            ->for($provider)
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
                'contact_owner_id' => $domainContact->id,
            ]);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.contact', ['domain' => self::DOMAIN]))->assertOk();

        $content = $response->json();

        self::assertIsArray($content);
        self::assertSame($domainContact->id, $content['id']);
    }

    #[Test]
    public function showNameserversForDomain(): void
    {
        $provider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'enabled' => true, 'default' => true, 'slug' => ProviderSlug::OPEN_PROVIDER]);

        $groupExtension = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
            'name' => ProductGroupType::EXTENSION,
        ]);

        $productNl = new ProductFactory()->createOne([
            'name' => '.nl',
            'slug' => 'extension_nl',
            'product_group_id' => $groupExtension->id,
        ]);

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => self::DOMAIN,
            'product_uuid' => $productNl->uuid,
        ]);

        new DomainDeploymentFactory()->createOne([
            'provider_id' => $provider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->actingAsEmployee()->getJson(
            $this->generateRoute('admin.domain.nameservers', ['domain' => self::DOMAIN])
        )->assertExactJson([
                    'nameservers' => [
                        [
                            'id' => '312592',
                            'seqNr' => '0',
                            'name' => 'ns1.customserver.nl',
                            'ip' => '52.57.114.204',
                            'ip6' => '2a05:d014:0f80:6e00:bde7:af96:9434:75d5',
                        ],
                        [
                            'id' => '312595',
                            'seqNr' => '1',
                            'name' => 'ns2.customserver.be',
                            'ip' => '52.214.115.96',
                            'ip6' => '2a05:d018:061d:bd00:21bc:c938:d548:dab1',
                        ],
                        [
                            'id' => '312598',
                            'seqNr' => '2',
                            'name' => 'ns3.customserver.eu',
                            'ip' => '52.56.134.244',
                            'ip6' => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                        ],
                        [
                            'id' => '312598',
                            'seqNr' => '3',
                            'name' => 'ns4.customserver.eu',
                            'ip' => '52.56.134.244',
                            'ip6' => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                        ],
                        [
                            'id' => '312598',
                            'seqNr' => '4',
                            'name' => 'ns5.customserver.eu',
                            'ip' => '52.56.134.244',
                            'ip6' => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                        ],
                        [
                            'id' => '312598',
                            'seqNr' => '5',
                            'name' => 'ns6.customserver.eu',
                            'ip' => '52.56.134.244',
                            'ip6' => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                        ],
                    ],
                ]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function showDnsReturnsCollection(): void
    {
        $aRecord = new ARecord('name', '127.0.0.1', 10, false);
        $cName = new CnameRecord('name', 'gangster.nl', 10, false);

        $dnsRecordCollection = new Collection([$aRecord, $cName]);

        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(
            self::once()
        )->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willReturn($dnsRecordCollection);

        $this->app->bind(DnsService::class, fn () => $dnsMock);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.dns.index', ['domain' => self::DOMAIN]))
            ->assertOk()
            ->assertJsonCount(2);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function showDnsZoneNotFound(): void
    {
        $dnsMock = self::createMock(DnsService::class);
        $dnsMock->expects(
            self::once()
        )->method('getDnsRecordsForDomain')
            ->with(self::DOMAIN)
            ->willThrowException(new DnsZoneNotFoundException('Dns zone not found'));

        $this->app->bind(DnsService::class, fn () => $dnsMock);

        $translator = self::resolve(TranslatorInterface::class);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.dns.index', ['domain' => self::DOMAIN]))
            ->assertNotFound()
            ->assertJson(['message' => $translator->translate('dns.dns-zone-not-exists'), 'errors' => []]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function showDomainProcessesForRtrDomain(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne(['domain' => self::DOMAIN]);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $processCollection = ProcessCollection::fromArray([
            [
                'id' => 12345,
                'user' => 'api-user',
                'customer' => 'customer-handle',
                'status' => 'RUNNING',
                'statusDetail' => 'Waiting for registry',
                'resumeTypes' => ['MANUAL'],
                'createdDate' => '2026-06-10T03:04:05Z',
                'updatedDate' => '2026-06-10T03:05:05Z',
                'startedDate' => '2026-06-10T03:04:10Z',
                'type' => 'domain',
                'identifier' => self::DOMAIN,
                'action' => 'transfer',
                'reservation' => ['id' => 100],
                'transaction' => ['id' => 200],
                'refund' => ['id' => 300],
                'command' => ['domain:transfer'],
            ],
        ]);

        $rtrService = self::createMock(RtrService::class);
        $rtrService->expects(
            self::once()
        )->method('listProcessesForDomain')
            ->with(self::DOMAIN)
            ->willReturn($processCollection);

        $domainServiceFactory = self::createMock(DomainServiceFactory::class);
        $domainServiceFactory->expects(
            self::once()
        )->method('driver')
            ->with(ProviderSlug::REALTIME_REGISTER, null)
            ->willReturn($rtrService);

        $this->app->bind(DomainServiceFactory::class, fn () => $domainServiceFactory);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.processes', ['domain' => self::DOMAIN]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $process = $response->json('data.0');

        self::assertIsArray($process);
        self::assertSame(12345, $process['id']);
        self::assertSame('RUNNING', $process['status']);
        self::assertSame(self::DOMAIN, $process['identifier']);
        self::assertSame('transfer', $process['action']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function showDomainProcessesReturnsUnprocessableForUnsupportedProvider(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->nlDomain())
            ->createOne(['domain' => self::DOMAIN]);

        new DomainDeploymentFactory()
            ->withOpenProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $domainServiceFactory = self::createMock(DomainServiceFactory::class);
        $domainServiceFactory->expects(
            self::never()
        )->method('driver');

        $this->app->bind(DomainServiceFactory::class, fn () => $domainServiceFactory);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.processes', ['domain' => self::DOMAIN]))
            ->assertUnprocessable()
            ->assertJson(['message' => 'Domain processes are not supported for this provider']);
    }
}
