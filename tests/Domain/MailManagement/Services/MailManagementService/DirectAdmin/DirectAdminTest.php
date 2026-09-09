<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services\MailManagementService\DirectAdmin;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Hosting\DirectAdmin\Mailer\MailDirectAdminDetails;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\MailManagement\Services\MailManagementDirectAdminService;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(MailManagementDirectAdminService::class)]
class DirectAdminTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'mytestdomain.com';

    private MailManagementDirectAdminService $mailOnlyDirectAdminService;

    private ProductGroup $hostingGroup;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $mailOnlyProduct = ProductFactory::new()->mailOnly()->createOne();
        new ProductSpecFactory()
            ->for($mailOnlyProduct)
            ->createOne([
                'name'  => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
                'value' => '1',
            ]);

        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($mailOnlyProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->createOne();

        $this->hostingGroup = $subscription->product->productGroup;

        $deployment = HostingDeploymentFactory::new()
            ->withMailOnlyProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        self::assertNotNull($deployment->mailOnlyServer);
        $this->server = $deployment->mailOnlyServer;

        $this->mailOnlyDirectAdminService = self::resolve(MailManagementDirectAdminService::class);
    }

    #[Test]
    public function getDirectAdminUsername(): void
    {
        $domain = 'welkom.nl';
        $customer = new CustomerFactory()->createOne();

        $product = new ProductFactory()->for($this->hostingGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'domain' => $domain,
            'customer_id' => $customer->id,
        ]);
        $hostingDeployment = new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'plesk_customer_username' => null,
            'directadmin_customer_username' => 'my-da-username',
        ]);

        $username = $this->mailOnlyDirectAdminService->getUsername($hostingDeployment);

        self::assertSame('my-da-username', $username);
    }

    /**
     * @throws GuzzleException
     */
    #[Test]
    public function createDomainMailOnly(): void
    {
        Event::fake([UpdateDns::class]);
        Queue::fake();

        new TemplateFactory()->createOne([
            'slug' => MailDirectAdminDetails::getTemplateSlug(),
        ]);

        $domain = self::TEST_DOMAIN;
        $email = 'john.doe@sandwave.io';
        $ipv4 = '127.0.0.1';
        $ipv6 = '::1';

        $mailProduct = new ProductFactory()->emailStart($this->hostingGroup)->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->for($mailProduct)->createOne([
            'domain' => $domain,
        ]);

        new HostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => null,
        ]);

        new ServerFactory()->directadmin()->createOne();

        $mailOnlyDirectAdminService = self::resolve(MailManagementDirectAdminService::class);
        $result = $mailOnlyDirectAdminService->createDomain($domain, $email);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame($this->server->id, $result->getServerId());
        Event::assertDispatched(UpdateDns::class);

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
}
