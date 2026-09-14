<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

/**
 * Tests for installing a SSL certificate.
 */
#[CoversClass(PleskHostingService::class)]
class InstallCertificateTest extends IntegrationTestCase
{
    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = new ServerFactory()->createOne(
            [
                'hostname' => 'plesk.testing.test',
                'ipv4' => '1.2.3.4',
                'ipv6' => '::1',
                'owner' => 'TestOwner',
                'allow_new_websites' => true,
                'maximum_websites' => 1,
                'type' => ServerType::PLESK,
            ],
        );
    }

    /**
     * Tests that no e-mail is sent to the servicedesk if the installation is successful.
     */
    #[DataProvider('serverProvider')]
    #[Test]
    public function noEmailOnSuccess(ServerType $serverType): void
    {
        $domain = 'sandwave.io';
        $hostingDeployment = $this->seedSubscriptions($domain, $this->server);
        $parentSubscription = $hostingDeployment->subscription;

        $mailDriver = self::resolve(Mailer::class);
        $symfonyTransport = $mailDriver->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $symfonyTransport);
        $emails = $symfonyTransport->messages();
        self::assertCount(0, $emails);
        $hostingService = self::resolve(PleskHostingService::class);
        $hostingService->installCertificate(
            $parentSubscription->uuid,
            [
                'domain' => $domain,
                'csr' => 'csr',
                'pvt' => 'pvt',
                'cert' => 'cert',
                'ca' => 'ca',
            ],
        );
        $emails = $symfonyTransport->messages();
        self::assertCount(0, $emails);
    }

    /**
     * Data provider for creating servers in tests.
     *
     * @return array<array<ServerType>>
     */
    public static function serverProvider(): array
    {
        return [
            [
                ServerType::PLESK,
            ],
        ];
    }

    /**
     * Seed the subscriptions for tests.
     */
    private function seedSubscriptions(string $domain, Server $server): HostingDeployment
    {
        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $productHosting = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'basic',
            'slug' => 'hosting_basic',
        ]);
        $parentSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($productHosting)
            ->createOne([
                'domain' => $domain,
                'gross_price' => 120,
                'net_price' => 120,
                'technical_status' => DomainStatus::ACTIVE->value,
                'contract_period' => 12,
                'billing_period' => 12,
            ]);
        $hostingDeployment = new HostingDeploymentFactory()->for($server)->createOne([
            'subscription_uuid' => $parentSubscription->uuid,
        ]);
        $hostingDeployment->subscription()->associate($parentSubscription);
        $hostingDeployment->server()->associate($server);
        $hostingDeployment->save();

        return $hostingDeployment;
    }
}
