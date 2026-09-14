<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services\MailManagementService\Plesk;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Plesk\Mailer\MailPleskEmailOnlyDetails;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Services\MailManagementPleskService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(MailManagementPleskService::class)]
class PleskTest extends IntegrationTestCase
{
    private MailManagementPleskService $mailOnlyPleskService;

    private string $email;

    private string $domain;

    private ProductGroup $hostingProductGroup;

    private Product $sitebuilderProduct;

    private Product $mailProduct;

    private Product $hostingBronsProduct;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->domain = 'mytestdomain.com';
        $this->email = 'john.doe@sandwave.io';

        $this->mailOnlyPleskService = self::resolve(MailManagementPleskService::class);

        new ProviderFactory()->createOne([
            'type' => ProviderType::HOSTING,
            'slug' => ProviderSlug::PLESK,
            'enabled' => true,
            'default' => true,
        ]);

        $this->hostingProductGroup = new ProductGroupFactory()->hosting()->createOne();

        $this->mailProduct = new ProductFactory()
            ->emailStart()
            ->for($this->hostingProductGroup)
            ->createOne();

        new ProductSpecFactory()->for($this->mailProduct)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '1',
        ]);

        $this->sitebuilderProduct = new ProductFactory()
            ->siteBuilder()
            ->for($this->hostingProductGroup)
            ->createOne();

        $this->hostingBronsProduct = new ProductFactory()
            ->hostingBrons()
            ->for($this->hostingProductGroup)
            ->createOne();

        $this->customer = new CustomerFactory()->createOne();
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function createDomainMailOnly(): void
    {
        Event::fake([UpdateDns::class]);

        self::assertEmailsSend([
            MailPleskEmailOnlyDetails::class,
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailPleskEmailOnlyDetails::getTemplateSlug(),
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => null,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
        ]);

        ProviderFactory::new()->create([
            'type' => ProviderType::MAILONLY,
            'default' => true,
            'enabled' => true,
            'slug' => ProviderSlug::PLESK,
        ]);

        $this->mailOnlyPleskService = self::resolve(MailManagementPleskService::class);
        $result = $this->mailOnlyPleskService->createDomain($this->domain, $this->email);

        self::assertSame(Result::STATUS_OK, $result->getStatus());

        $usedServer = Server::findOrFail($result->getServerId());
        self::assertSame($server->type, $usedServer->type);

        Event::assertDispatched(UpdateDns::class);

        $domain = 'test.nl';
        $ipv4 = '127.0.0.1';
        $ipv6 = '::1';

        $dnsZoneService = self::resolve(DnsZoneService::class);
        $dnsZone = $dnsZoneService->getHostingDnsRecords($domain, $ipv4, $ipv6);

        $records = $dnsZone->getAddedRows();

        foreach ($records as $record) {
            self::assertStringContainsString($domain, $record->getDnsRecord()->getName());

            if ($record->getDnsRecord()->getType() === 'A') {
                self::assertSame($ipv4, $record->getDnsRecord()->getContent());
            } elseif ($record->getDnsRecord()->getType() === 'AAAA') {
                self::assertSame($ipv6, $record->getDnsRecord()->getContent());
            }
        }
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function createDomainSiteBuilder(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->sitebuilderProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => null,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
        ]);

        ProviderFactory::new()->create([
            'type' => ProviderType::MAILONLY,
            'default' => true,
            'enabled' => true,
            'slug' => ProviderSlug::PLESK,
        ]);

        Event::fake([UpdateDns::class]);
        Event::assertNotDispatched(UpdateDns::class);

        $result = $this->mailOnlyPleskService->createDomain($this->domain, $this->email);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame($server->id, $result->getServerId());
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function createDomainMissingIPv4(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->sitebuilderProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => null,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
            'ipv4' => null,
        ]);

        $this->expectException(MailOnlyException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Server %s does not have an IPv4 or IPv6 address.',
            $server->id,
        ));

        $this->mailOnlyPleskService->createDomain($this->domain, $this->email);
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function createDomainMissingIPv6(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->sitebuilderProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => null,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
            'ipv6' => null,
        ]);

        $this->expectException(MailOnlyException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Server %s does not have an IPv4 or IPv6 address.',
                $server->id,
            ),
        );

        $this->mailOnlyPleskService->createDomain($this->domain, $this->email);
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function expectsModelNotFoundException(): void
    {
        new SubscriptionFactory()
            ->for($this->mailProduct)
            ->for($this->customer)
            ->createOne([
                'domain' => 'i-dont-exists.nl',
            ]);

        $this->expectException(ModelNotFoundException::class);

        $this->mailOnlyPleskService->createDomain($this->domain, $this->email);
    }

    /**
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function expectsNotImplementedException(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->hostingBronsProduct)
            ->for($product)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->expectExceptionMessageIs(
            sprintf(
                'Plesk mail-only does only support sitebuilder, subscription %s is not a sitebuilder subscription.',
                $subscription->uuid,
            ),
        );

        $this->expectException(NotImplementedException::class);

        $this->mailOnlyPleskService->createDomain($this->domain, $this->email);
    }

    /**
     * @see https://yh-jira.atlassian.net/browse/WATER-3963
     *
     * @throws PleskClientException
     * @throws SubscriptionNotFoundException
     */
    #[Test]
    public function createDomainAfterCancelingDomain(): void
    {
        $this->app->bind(DnsService::class, fn (): DnsService => self::createStub(DnsService::class));

        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        // Customer has a hosting package but canceled it (start/brons)
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->hostingBronsProduct)
            ->for($product)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
                'cancel_date' => CarbonImmutable::now(),
                'domain' => $this->domain,
            ]);

        // Customer re-orders a new hosting package (e-mail)
        $subscription2 = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailProduct)
            ->createOne([
                'uuid' => 'abcde-abcde-abcde-abcde',
                'domain' => $this->domain,
            ]);

        $subscription2->refresh();

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription2->uuid,
        ]);

        $server = new ServerFactory()->createOne([
            'type' => ServerType::PLESK,
        ]);

        ProviderFactory::new()->create([
            'type' => ProviderType::MAILONLY,
            'slug' => ProviderSlug::PLESK,
            'default' => true,
            'enabled' => true,
        ]);

        $result = $this->mailOnlyPleskService->createDomain($this->domain, $this->customer->email);

        self::assertSame('ok', $result->getStatus());
        self::assertSame($server->id, $result->getServerId());
    }

    #[Test]
    public function deleteDomain(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->sitebuilderProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        ProviderFactory::new()->create([
            'type' => ProviderType::MAILONLY,
            'default' => true,
            'enabled' => true,
            'slug' => ProviderSlug::PLESK,
        ]);

        new ServerFactory()->createOne(['type' => ServerType::PLESK]);

        $result = $this->mailOnlyPleskService->deleteDomain('', $this->domain, $this->email);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function expectsModelNotFoundExceptionDeleteDomain(): void
    {
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailProduct)
            ->createOne([
                'domain' => 'not-exists.nl',
            ]);

        $this->expectException(ModelNotFoundException::class);

        $this->mailOnlyPleskService->deleteDomain('', $this->domain, $this->email);
    }

    #[Test]
    public function expectsNotImplementedExceptionDeleteDomain(): void
    {
        $product = new ProductFactory()->for($this->hostingProductGroup)->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailProduct)
            ->for($product)
            ->createOne([
                'domain' => $this->domain,
            ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
        ]);

        $this->expectExceptionMessageIs(
            sprintf(
                'Plesk mail-only does only support sitebuilder, subscription %s is not a sitebuilder subscription.',
                $subscription->uuid,
            ),
        );

        $this->expectException(NotImplementedException::class);

        $this->mailOnlyPleskService->deleteDomain('', $this->domain, $this->email);
    }

    #[Test]
    public function getPleskUsername(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($this->mailProduct)
            ->createOne([
                'domain' => $this->domain,
            ]);

        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => 'my-plesk-username',
            'directadmin_customer_username' => null,
        ]);

        $username = $this->mailOnlyPleskService->getUsername($hostingDeployment);

        self::assertSame('my-plesk-username', $username);
    }
}
