<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services\MailManagementService\DirectAdmin;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Jobs\CleanupMailOnlyDns;
use Waterfront\Domain\MailManagement\Jobs\ConfigureMailOnlyDns;
use Waterfront\Domain\MailManagement\Services\MailManagementDirectAdminService;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResponseException;

#[CoversClass(MailManagementDirectAdminService::class)]
class ManageDomainTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'mytestdomain.com';

    private MailManagementDirectAdminService $mailOnlyService;

    private Server $server;

    private HostingDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->mailOnly())
            ->forDomain(self::TEST_DOMAIN)
            ->createOne();

        $this->deployment = HostingDeploymentFactory::new()
            ->withMailOnlyProvider()
            ->createOne([
                'subscription_uuid' => $sub->uuid,
            ]);

        self::assertNotNull($this->deployment->mailOnlyServer);
        $this->server = $this->deployment->mailOnlyServer;

        $this->mailOnlyService = self::resolve(MailManagementDirectAdminService::class);
    }

    #[Test]
    public function listDomain(): void
    {
        $hostname   = 'mytestserverhostname.com';
        $domain     = self::TEST_DOMAIN;
        $domainUser = 'testowner';

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type'     => ServerType::DIRECTADMIN_MAIL,
        ]);

        $result = $this->mailOnlyService->listDomain($hostname, $domain, $domainUser);

        self::assertSame(
            'mytestuser1',
            Arr::get($result, 'users.0'),
            "Unable to find the provided user 'mytestuser1' in the listDomain response"
        );

        self::assertSame(
            'mytestuser2',
            Arr::get($result, 'users.1'),
            "Unable to find the provided user 'mytestuser2' in the listDomain response"
        );
    }

    #[Test]
    public function createDomain(): void
    {
        $hostname   = 'mytestserverhostname.com';
        $email      = 'mark@sandwave.io';
        $domain     = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type'     => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->mailOnlyService = self::resolve(MailManagementDirectAdminService::class);
        $result = $this->mailOnlyService->createDomain($domain, $email);

        self::assertSame('ok', $result->getStatus());
        self::assertNotNull($result->getResourceId());

        Queue::assertPushed(ConfigureMailOnlyDns::class);
    }

    #[Test]
    public function createDomainThrowsException(): void
    {
        $hostname   = 'mytestserverhostname.com';
        $email      = 'mark@sandwave.io';
        $domain     = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type'     => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->app->extend(BehavesAsDirectAdmin::class, function () {
            $daMock = self::createMock(DirectAdmin::class);
            $daApiMock = self::createMock(DirectAdminApi::class);
            $daMock->expects(self::once())->method('useServer')->willReturn($daApiMock);
            $daApiMock->expects(self::once())->method('call')->willThrowException(new DirectAdminResponseException('Error'));
            return $daMock;
        });

        $service = self::resolve(MailManagementDirectAdminService::class);
        $this->expectException(MailOnlyException::class);
        $service->createDomain($domain, $email);
    }

    #[Test]
    public function createDomainThrowsExceptionUnsetIpOnServer(): void
    {
        $email      = 'mark@sandwave.io';
        $domain     = self::TEST_DOMAIN;

        $this->server->ipv4 = null;
        $this->server->save();

        $this->expectException(MailOnlyException::class);
        $this->mailOnlyService->createDomain($domain, $email);
    }

    #[Test]
    public function deleteDomain(): void
    {
        $hostname   = 'mytestserverhostname.com';
        $domainUser = 'testowner';
        $domain     = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type'     => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->mailOnlyService = self::resolve(MailManagementDirectAdminService::class);
        $result = $this->mailOnlyService->deleteDomain($hostname, $domain, $domainUser);

        self::assertSame('ok', $result->getStatus());

        Queue::assertPushed(CleanupMailOnlyDns::class);
    }

    #[Test]
    public function deleteDomainThrowsException(): void
    {
        $hostname   = 'mytestserverhostname.com';
        $domainUser = 'testowner';
        $domain     = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type'     => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->app->extend(BehavesAsDirectAdmin::class, function () {
            $daMock = self::createMock(DirectAdmin::class);
            $daApiMock = self::createMock(DirectAdminApi::class);
            $daMock->expects(self::once())->method('useServer')->willReturn($daApiMock);
            $daApiMock->expects(self::once())->method('call')->willThrowException(new DirectAdminResponseException('Error'));
            return $daMock;
        });

        $service = self::resolve(MailManagementDirectAdminService::class);
        $this->expectException(MailOnlyException::class);
        $service->deleteDomain($hostname, $domain, $domainUser);
    }

    #[Test]
    public function unconfiguredServer(): void
    {
        $email      = 'mark@sandwave.io';
        $domain     = self::TEST_DOMAIN;

        $this->deployment->subscription->forceDelete();
        $this->deployment->forceDelete();

        $this->expectException(ModelNotFoundException::class);
        $this->mailOnlyService->createDomain($domain, $email);
    }
}
