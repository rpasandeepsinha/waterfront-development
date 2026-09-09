<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\DirectAdmin;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\DirectAdmin\Exceptions\CoupleHostingException;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectAdminHostingService;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\Commands\Domains\DeleteDomains;
use Waterfront\Infra\DirectAdminClient\Commands\Users\ShowUserStats;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DirectAdminApiInterface;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(DirectAdminHostingService::class)]
class DirectAdminHostingCoupleTest extends IntegrationTestCase
{
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = new ProductFactory()->for(new ProductGroupFactory()->hosting())->createOne();
    }

    /**
     *
     * @throws DirectAdminCommandException
     * @throws CoupleHostingException
     * @throws JsonException
     *
     * @see DirectAdminHostingService::decoupleHostingByDomain()
     */
    #[Test]
    public function decoupleDomainFromMissingHostingException(): void
    {
        $domainName = 'remove-me.nl';

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $this->expectException(CoupleHostingException::class);
        $this->expectExceptionMessageIs("Could not find any coupled hosting for $domainName in Domain deployment $domainDeployment->id with Subscription $domainSubscription->uuid");

        $directAdminHostingService->decoupleHostingByDomain($domainDeployment);
    }

    /**
     *
     * @throws DirectAdminCommandException
     * @throws CoupleHostingException
     * @throws JsonException
     *
     * @see DirectAdminHostingService::decoupleHostingByDomain()
     */
    #[Test]
    public function decoupleDomainFromHostingCommandFailException(): void
    {
        $domainName = 'remove-me.nl';

        $rawDirectAdminResponseDomains = file_get_contents(__DIR__ . '/data/delete-domain/UserStats.json');

        /** @var array<mixed,mixed> $directAdminResponseDomains */
        $directAdminResponseDomains = [];
        if ($rawDirectAdminResponseDomains !== false) {
            /** @var array<mixed,mixed> $directAdminResponseDomains */
            $directAdminResponseDomains = json_decode(json: $rawDirectAdminResponseDomains, associative: true, flags: JSON_THROW_ON_ERROR);
        }

        $deleteErrorResponse = '{"message":"Something went wrong on DirectAdmin"}';

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $hostingSubscription = new SubscriptionFactory()
            ->has((new HostingDeploymentFactory()))
            ->for($this->product)
            ->for($domainSubscription->customer)
            ->createOne([
                'domain' => $domainName,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock->expects(self::exactly(2))
            ->method('useServer')
            ->willReturnCallback(fn (Server $server) => $server->id === $hostingDeployment->server?->id ? $directAdminApiMock : null);

        $directAdminApiMock->expects(self::once())
            ->method('loginAs')
            ->willReturnCallback(fn (string $username) => $username === $hostingDeployment->directadmin_customer_username ? $directAdminApiMock : null);

        $deleteDomainCommand = new DeleteDomains();
        $deleteDomainCommand->responseReceived(json_decode(json: $deleteErrorResponse, associative: true, flags: JSON_THROW_ON_ERROR));

        $showUserStatsCommand = new ShowUserStats()->responseReceived($directAdminResponseDomains);
        $directAdminApiMock->expects(self::exactly(2))
            ->method('call')
            ->willReturnCallback(fn ($command) => match (true) {
                $command instanceof ShowUserStats => $showUserStatsCommand,
                $command instanceof DeleteDomains => $deleteDomainCommand,
                default => throw new LogicException()
            });

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIsOrContains("Could not remove Domaindeployment $domainDeployment->id ($domainName) from Hostingdeployment $hostingDeployment->id.");

        $directAdminHostingService->decoupleHostingByDomain($domainDeployment);
    }

    /**
     *
     * @throws DirectAdminCommandException
     * @throws CoupleHostingException
     * @throws JsonException
     *
     * @see DirectAdminHostingService::decoupleHostingByDomain()
     */
    #[Test]
    public function decoupleDomainFromHosting(): void
    {
        $domainName = 'remove-me.nl';

        $rawDirectAdminResponseDomains = file_get_contents(__DIR__ . '/data/delete-domain/UserStats.json');
        $directAdminResponseDomains = [];
        if ($rawDirectAdminResponseDomains !== false) {
            $directAdminResponseDomains = json_decode(json: $rawDirectAdminResponseDomains, associative: true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($directAdminResponseDomains));
        }

        $rawDirectAdminResponseDelete = file_get_contents(__DIR__ . '/data/delete-domain/DeleteDomains.json');
        $directAdminResponseDelete = [];
        if ($rawDirectAdminResponseDelete !== false) {
            $directAdminResponseDelete = json_decode(json: $rawDirectAdminResponseDelete, associative: true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($directAdminResponseDelete));
        }

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($domainSubscription->customer)
            ->has((new HostingDeploymentFactory()))
            ->createOne([
                'domain' => $domainName,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock->expects(self::exactly(2))
            ->method('useServer')
            ->with(self::callback(function ($server) use ($hostingDeployment): bool {
                $hostingServer = $hostingDeployment->server;
                self::assertInstanceOf(Server::class, $hostingServer);
                return $server->id === $hostingServer->id;
            }))
            ->willReturn($directAdminApiMock);

        $deleteDomainCommand = new DeleteDomains();
        self::assertFalse($deleteDomainCommand->hasSucceeded());

        $directAdminApiMock->expects(self::once())
            ->method('loginAs')
            ->with(self::callback(fn ($username): bool => $username === $hostingDeployment->directadmin_customer_username))
            ->willReturn($directAdminApiMock);

        $directAdminApiMock->expects(self::exactly(2))
            ->method('call')
            ->willReturnCallback(fn ($command) => match (true) {
                $command instanceof ShowUserStats => new ShowUserStats()->responseReceived($directAdminResponseDomains),
                $command instanceof DeleteDomains => $deleteDomainCommand->responseReceived($directAdminResponseDelete),
                default => throw new LogicException()
            });

        $directAdminHostingService->decoupleHostingByDomain($domainDeployment);

        self::assertTrue($deleteDomainCommand->hasSucceeded());
    }

    /**
     *
     * @throws DirectAdminCommandException
     * @throws CoupleHostingException
     *
     * @see DirectAdminHostingService::getCoupledHostingByDomain()
     */
    #[Test]
    public function domainIsCoupledToHostingSuccessIfFound(): void
    {
        $domainName = 'couple-me-to-hosting.nl';

        $rawDirectAdminResponse = file_get_contents(__DIR__ . '/data/UserStats.json');
        $directAdminResponse = [];
        if ($rawDirectAdminResponse !== false) {
            /** @var array<int,mixed> $directAdminResponse */
            $directAdminResponse = json_decode(json: $rawDirectAdminResponse, associative: true, flags: JSON_THROW_ON_ERROR);
            $directAdminResponse['domains'][0]['domain'] = $domainName;
        }

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $hostingSubscription = new SubscriptionFactory()
            ->has((new HostingDeploymentFactory()))
            ->for($this->product)
            ->for($domainSubscription->customer)
            ->createOne([
                'domain' => $domainName,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock->expects(self::once())
            ->method('useServer')
            ->with(self::callback(function ($server) use ($hostingDeployment): bool {
                $hostingServer = $hostingDeployment->server;
                self::assertInstanceOf(Server::class, $hostingServer);
                return $server->id === $hostingServer->id;
            }))
            ->willReturn($directAdminApiMock);

        $directAdminApiMock->expects(self::once())
            ->method('call')
            ->with(self::callback(fn ($command): bool => $command instanceof ShowUserStats))
            ->willReturn(new ShowUserStats()->responseReceived($directAdminResponse));

        $coupledHostingDeployment = $directAdminHostingService->getCoupledHostingByDomain($domainDeployment);
        self::assertNotNull($coupledHostingDeployment);
        self::assertSame($hostingDeployment->uuid, $coupledHostingDeployment->uuid);
        self::assertSame($domainName, $coupledHostingDeployment->subscription->domain);
    }

    /**
     * @throws DirectAdminCommandException|GuzzleException
     *
     * @see DirectAdminHostingService::getCoupledHostingByDomain()
     */
    #[Test]
    public function returnsNullHostingWithoutDirectAdminUsername(): void
    {
        $domainName = 'couple-me-to-hosting.nl';

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        new SubscriptionFactory()
            ->has(new HostingDeploymentFactory()->state([
                'directadmin_customer_username' => null,
            ]))
            ->for($domainSubscription->customer)->for($this->product)->createOne();
        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        self::assertNull($directAdminHostingService->getCoupledHostingByDomain($domainDeployment));
    }

    /**
     * @throws DirectAdminCommandException|GuzzleException
     *
     * @see DirectAdminHostingService::getCoupledHostingByDomain()
     */
    #[Test]
    public function returnsNullHostingWithoutDirectAdminServer(): void
    {
        $domainName = 'couple-me-to-hosting.nl';

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()), 'domainDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        new SubscriptionFactory()
            ->has(new HostingDeploymentFactory()->state([
                'directadmin_customer_username' => 'test',
                'server_id' => null,
            ]), 'hostingDeployment')
            ->for($domainSubscription->customer)->for($this->product)->createOne();

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        self::assertNull($directAdminHostingService->getCoupledHostingByDomain($domainDeployment));
    }

    /**
     *
     * @throws CoupleHostingException
     * @throws DirectAdminCommandException
     * @throws JsonException
     *
     * @see DirectAdminHostingService::getCoupledHostingByDomain()
     */
    #[Test]
    public function domainIsCoupledToHostingSuccessIfNotFound(): void
    {
        $domainName = 'couple-me-to-hosting.nl';

        $rawDirectAdminResponse = file_get_contents(__DIR__ . '/data/UserStats.json');
        $directAdminResponse = [];
        if ($rawDirectAdminResponse !== false) {
            $directAdminResponse = json_decode(json: $rawDirectAdminResponse, associative: true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($directAdminResponse));
        }

        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne()))
            ->createOne([
                'domain' => $domainName,
            ]);

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $hostingSubscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($domainSubscription->customer)
            ->has((new HostingDeploymentFactory()), 'hostingDeployment')
            ->createOne([
                'domain' => $domainName,
            ]);

        $hostingDeployment = $hostingSubscription->hostingDeployment;
        self::assertInstanceOf(HostingDeployment::class, $hostingDeployment);

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        // We expect the directadmin client to use the server we have attached to our hosting deployment
        $directAdminMock->expects(self::once())
            ->method('useServer')
            ->with(self::callback(function ($server) use ($hostingDeployment): bool {
                $hostingServer = $hostingDeployment->server;
                self::assertInstanceOf(Server::class, $hostingServer);
                return $server->id === $hostingServer->id;
            }))->willReturn($directAdminApiMock);

        $directAdminApiMock->expects(self::once())
            ->method('call')
            ->with(self::callback(fn ($command): bool => $command instanceof ShowUserStats))
            ->willReturn(new ShowUserStats()->responseReceived($directAdminResponse));

        $coupledHostingDeployment = $directAdminHostingService->getCoupledHostingByDomain($domainDeployment);
        self::assertNull($coupledHostingDeployment);
    }

    /**
     * @throws CoupleHostingException
     *
     * @see DirectAdminHostingService::getCoupledHostingByDomain()
     */
    #[Test]
    public function domainIsCoupledToHostingSuccessIfNoHostingSubscriptions(): void
    {
        $directAdminMock = self::createMock(DirectAdmin::class);
        $directAdminApiMock = self::createMock(DirectAdminApi::class);

        $this->app->bind(DirectAdminApiInterface::class, fn () => $directAdminApiMock);
        $this->app->bind(BehavesAsDirectAdmin::class, fn () => $directAdminMock);

        $domainSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)
            ->has(new DomainDeploymentFactory()->withPlaceholderProvider(), 'domainDeployment')
            ->createOne();

        $domainDeployment = $domainSubscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $directAdminHostingService = self::resolve(DirectAdminHostingService::class);

        // We should never call DirectAdmin if we don't have any hosting packages.
        $directAdminMock->expects(self::never())->method('useServer');
        $directAdminApiMock->expects(self::never())->method('call');

        $coupledHostingDeployment = $directAdminHostingService->getCoupledHostingByDomain($domainDeployment);
        self::assertNull($coupledHostingDeployment);
    }
}
