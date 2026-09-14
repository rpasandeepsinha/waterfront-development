<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DNS;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DnsController;
use Waterfront\Apps\API\Waterfront\Policies\DnsPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Dns\StoreRequest;
use Waterfront\Apps\API\Waterfront\Requests\Dns\UpdateRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Redirects\Actions\DeleteRedirectForDomainAction;
use Waterfront\Domain\Redirects\Mappers\RedirectDnsSubscriptionMapper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * Todo : To achieve complete coverage, this test must be further expanded.
 * At the moment, the sendNotify is only being tested.
 * See : https://yh-jira.atlassian.net/browse/WATER-6384.
 */
#[CoversClass(DnsController::class)]
#[AllowMockObjectsWithoutExpectations]
class DnsControllerTest extends IntegrationTestCase
{
    private const string REDIRECT_DNS = 'sandwaveio.dev';

    private const string REDIRECT_IPV4 = '127.0.0.1';

    private const string REDIRECT_IPV6 = '::1';

    private string $testDomain = 'test-domain.nl';

    private Customer $customer;

    private Subscription $dnsSubscription;

    private DnsController $dnsController;

    private DeleteRedirectForDomainAction&MockObject $deleteRedirectForDomainActionMock;

    private DnsService&MockObject $dnsServiceMock;

    private Product $premiumDnsProduct;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->actingAsCustomer($this->customer);

        $domainProduct = new ProductFactory()->nlDomain()->createOne();

        $domainSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($domainProduct)
            ->forDomain($this->testDomain)
            ->createOne();

        $dnsProductGroup = new ProductGroupFactory()->dns()->createOne();

        $dnsProduct = new ProductFactory()
            ->for($dnsProductGroup)
            ->has(
                new ProductSpecFactory()->state([
                    'name' => ProductSpecName::DNS_CAN_EDIT_RECORDS->value,
                    'value' => true,
                ]),
            )
            ->createOne();

        $this->dnsSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($dnsProduct)
            ->forDomain($this->testDomain)
            ->parentSubscription($domainSubscription)
            ->createOne();

        $this->premiumDnsProduct = new ProductFactory()->for($dnsProductGroup)->createOne();
        new ProductSpecFactory()->for($this->premiumDnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $this->deleteRedirectForDomainActionMock = self::createMock(DeleteRedirectForDomainAction::class);

        $this->dnsServiceMock = self::createMock(DnsService::class);

        $this->dnsController = new DnsController(
            subscriptionService: self::resolve(SubscriptionService::class),
            dnsPolicy: self::createStub(DnsPolicy::class),
            translator: self::resolve(TranslatorInterface::class),
            deleteRedirectForDomain: self::createStub(DeleteRedirectForDomainAction::class),
            dnsService: $this->dnsServiceMock,
            dnsProductSpecRepository: self::resolve(DnsProductSpecRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            redirectDnsMapper: self::resolve(RedirectDnsSubscriptionMapper::class),
        );
    }

    #[Test]
    public function storeNotSendNotify(): void
    {
        $this->dnsServiceMock->expects(self::never())->method('sendNotify');

        $result = $this->dnsController->store(new StoreRequest(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function storePremiumDnsSendNotify(): void
    {
        $this->dnsSubscription->product()->associate($this->premiumDnsProduct)->save();

        $this->dnsServiceMock->expects(self::once())->method('sendNotify');

        $result = $this->dnsController->store(new StoreRequest(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function updateNotSendNotify(): void
    {
        $this->dnsServiceMock->expects(self::never())->method('sendNotify');

        $result = $this->dnsController->update(new UpdateRequest(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function updateSendNotify(): void
    {
        $this->dnsSubscription->product()->associate($this->premiumDnsProduct)->save();

        $this->dnsServiceMock->expects(self::once())->method('sendNotify');

        $result = $this->dnsController->update(new UpdateRequest(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function destroyNotSendNotify(): void
    {
        $this->dnsServiceMock->expects(self::never())->method('sendNotify');

        $result = $this->dnsController->destroy(new Request(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function destroySendNotify(): void
    {
        $this->dnsSubscription->product()->associate($this->premiumDnsProduct)->save();

        $this->dnsServiceMock->expects(self::once())->method('sendNotify');

        $result = $this->dnsController->destroy(new Request(), $this->testDomain);
        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function dnsStoreNameserverRecord(): void
    {
        $postData = [
            'domain' => $this->testDomain,
            'name' => 'ns1.' . $this->testDomain,
            'type' => DnsRecordType::NS->value,
            'content' => $this->testDomain,
            'ttl' => 600,
            'disabled' => false,
        ];

        $this->app->bind(DnsService::class, fn () => $this->dnsServiceMock);

        $this->dnsServiceMock->expects(self::once())->method('addRecordFromArray')->with($this->testDomain);

        $this->actingAsCustomer($this->customer)
            ->post(
                $this->generateRoute('partners.dns.store', ['domain' => $this->testDomain]),
                $postData,
            )
            ->assertOk()
            ->assertExactJson([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function dnsStoreInvalidNameServerRecord(): void
    {
        $postData = [
            'domain' => $this->testDomain,
            'name' => $this->testDomain,
            'type' => DnsRecordType::NS->value,
            'content' => $this->testDomain,
            'ttl' => 600,
            'disabled' => false,
        ];

        $this->actingAsCustomer($this->customer)
            ->post(
                $this->generateRoute('partners.dns.store', ['domain' => $this->testDomain]),
                $postData,
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'name' => ['validation.ns'],
            ]);
    }

    #[Test]
    public function dnsUpdateNameserverRecord(): void
    {
        $postData = [
            'domain' => $this->testDomain,
            'old' => [
                'name' => 'ns1.' . $this->testDomain,
                'type' => DnsRecordType::NS->value,
                'content' => $this->testDomain,
                'ttl' => 600,
                'disabled' => false,
            ],
            'new' => [
                'name' => 'ns2.' . $this->testDomain,
                'type' => DnsRecordType::NS->value,
                'content' => $this->testDomain,
                'ttl' => 600,
                'disabled' => false,
            ],
        ];

        $this->app->bind(DeleteRedirectForDomainAction::class, fn () => $this->deleteRedirectForDomainActionMock);

        $this->deleteRedirectForDomainActionMock
            ->expects(self::once())
            ->method('execute')
            ->with(
                self::callback(fn (Subscription $subscription) => $subscription->is($this->dnsSubscription)),
                self::equalTo($postData['old']),
                self::equalTo($postData['new']),
            );

        $this->app->bind(DnsService::class, fn () => $this->dnsServiceMock);

        $this->dnsServiceMock
            ->expects(self::once())
            ->method('prepareUpdateRecord')
            ->with($this->testDomain, $postData['old'], $postData['new']);

        $this->actingAsCustomer($this->customer)
            ->patch(
                $this->generateRoute('partners.dns.update', ['domain' => $this->testDomain, 'dns' => 1]),
                $postData,
            )
            ->assertOk()
            ->assertExactJson([
                'message' => 'success',
            ]);
    }

    #[Test]
    public function dnsUpdateInvalidNameServerRecord(): void
    {
        $postData = [
            'domain' => $this->testDomain,
            'old' => [
                'name' => 'ns1' . $this->testDomain,
                'type' => DnsRecordType::NS->value,
                'content' => $this->testDomain,
                'ttl' => 600,
                'disabled' => false,
            ],
            'new' => [
                'name' => $this->testDomain,
                'type' => DnsRecordType::NS->value,
                'content' => $this->testDomain,
                'ttl' => 600,
                'disabled' => false,
            ],
        ];

        $this->actingAsCustomer($this->customer)
            ->patch(
                $this->generateRoute('partners.dns.update', ['domain' => $this->testDomain, 'dns' => 1]),
                $postData,
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'new.name' => ['validation.ns'],
            ]);
    }

    #[Test]
    public function indexReturnsDnsRecordsWithAndWithoutRedirectUuid(): void
    {
        $redirectSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->redirect())
            ->forDomain($this->testDomain)
            ->createOne();

        $redirectUuid = $redirectSubscription->uuid;

        $recordWithRedirect = new DefaultRecord('CNAME', 'www.' . $this->testDomain, 'sandwaveio.dev', 1200);
        $recordWithoutRedirect = new DefaultRecord('A', 'mail.' . $this->testDomain, '1.2.3.4', 600);

        $this->dnsServiceMock
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with($this->testDomain)
            ->willReturn(new Collection([$recordWithRedirect, $recordWithoutRedirect]));

        $this->app->bind(DnsController::class, fn () => $this->dnsController);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.dns.index', ['domain' => $this->testDomain]),
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'redirect_uuid' => $redirectUuid,
                'name' => 'www.' . $this->testDomain,
                'type' => 'CNAME',
            ])
            ->assertJsonFragment([
                'redirect_uuid' => null,
                'name' => 'mail.' . $this->testDomain,
                'type' => 'A',
            ]);
    }

    #[Test]
    public function indexDoesNotLabelAddressRecordsPointingAtTheRedirectServiceAsRedirect(): void
    {
        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->redirect())
            ->forDomain($this->testDomain)
            ->createOne();

        $aRecord = new DefaultRecord('A', $this->testDomain, self::REDIRECT_IPV4, 1200);
        $aaaaRecord = new DefaultRecord('AAAA', $this->testDomain, self::REDIRECT_IPV6, 1200);

        $this->dnsServiceMock
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with($this->testDomain)
            ->willReturn(new Collection([$aRecord, $aaaaRecord]));

        $this->app->bind(DnsController::class, fn () => $this->dnsController);

        $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.dns.index', ['domain' => $this->testDomain]),
            )
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'redirect_uuid' => null,
                'name' => $this->testDomain,
                'type' => 'A',
            ])
            ->assertJsonFragment([
                'redirect_uuid' => null,
                'name' => $this->testDomain,
                'type' => 'AAAA',
            ]);
    }

    #[Test]
    public function indexDoesNotLabelAddressRecordsPointingAtALegacyRedirectingServerAsRedirect(): void
    {
        $legacyServer = LegacyRedirectingServerFactory::new()->createOne();
        self::assertNotNull($legacyServer->ipv6);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->redirect())
            ->forDomain($this->testDomain)
            ->createOne();

        $aRecord = new DefaultRecord('A', $this->testDomain, $legacyServer->ipv4, 1200);
        $aaaaRecord = new DefaultRecord('AAAA', $this->testDomain, $legacyServer->ipv6, 1200);
        $aliasRecord = new DefaultRecord('ALIAS', $this->testDomain, self::REDIRECT_DNS, 1200);

        $this->dnsServiceMock
            ->expects(self::once())
            ->method('getDnsRecordsForDomain')
            ->with($this->testDomain)
            ->willReturn(new Collection([$aRecord, $aaaaRecord, $aliasRecord]));

        $this->app->bind(DnsController::class, fn () => $this->dnsController);

        $response = $this->actingAsCustomer($this->customer)
            ->get(
                $this->generateRoute('partners.dns.index', ['domain' => $this->testDomain]),
            )
            ->assertOk()
            ->assertJsonCount(3, 'data');

        self::assertNull($response->json('data.0.redirect_uuid'));
        self::assertNull($response->json('data.1.redirect_uuid'));
        self::assertNotNull($response->json('data.2.redirect_uuid'));
    }
}
