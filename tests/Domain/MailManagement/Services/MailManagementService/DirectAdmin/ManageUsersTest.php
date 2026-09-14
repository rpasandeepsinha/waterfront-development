<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services\MailManagementService\DirectAdmin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\MailManagement\Services\MailManagementDirectAdminService;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdmin;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminResponseException;

#[CoversClass(MailManagementDirectAdminService::class)]
class ManageUsersTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'mytestdomain.com';

    protected function setUp(): void
    {
        parent::setUp();

        $sub = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->mailOnly())
            ->forDomain(self::TEST_DOMAIN)
            ->createOne();

        HostingDeploymentFactory::new()->withMailOnlyProvider()->createOne([
            'subscription_uuid' => $sub->uuid,
        ]);
    }

    #[Test]
    public function createUser(): void
    {
        $hostname = 'mytestserverhostname.com';
        $email = 'testmail';
        $password = 'testpassword1123';
        $domainUsername = 'testusername';
        $domain = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);
        $service = self::resolve(MailManagementDirectAdminService::class);
        $result = $service->createUser(
            $hostname,
            $domain,
            $domainUsername,
            $email,
            $password,
            444,
            999,
        );

        self::assertSame('ok', $result->getStatus());
    }

    #[Test]
    public function createUserThrowsException(): void
    {
        $hostname = 'mytestserverhostname.com';
        $email = 'testmail';
        $password = 'testpassword1123';
        $domainUsername = 'testusername';
        $domain = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->app->extend(BehavesAsDirectAdmin::class, function () {
            $daMock = self::createMock(DirectAdmin::class);
            $daApiMock = self::createMock(DirectAdminApi::class);
            $daMock->expects(self::once())->method('useServer')->willReturn($daApiMock);
            $daApiMock->expects(self::once())->method('loginAs')->willReturn($daApiMock);
            $daApiMock
                ->expects(self::once())
                ->method('call')
                ->willThrowException(new DirectAdminResponseException('Error'));

            return $daMock;
        });

        $service = self::resolve(MailManagementDirectAdminService::class);
        $this->expectException(MailOnlyException::class);
        $service->createUser(
            $hostname,
            $domain,
            $domainUsername,
            $email,
            $password,
            444,
            999,
        );
    }

    #[Test]
    public function deleteUser(): void
    {
        $hostname = 'mytestserverhostname.com';
        $domainUser = 'testowner';
        $mailPopImapUser = 'testpopuser';
        $domain = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);

        $service = self::resolve(MailManagementDirectAdminService::class);
        $result = $service->deleteUser($hostname, $domain, $domainUser, $mailPopImapUser);

        self::assertSame('ok', $result->getStatus());
    }

    #[Test]
    public function deleteUserThrowsException(): void
    {
        $hostname = 'mytestserverhostname.com';
        $domainUser = 'testowner';
        $mailPopImapUser = 'testpopuser';
        $domain = self::TEST_DOMAIN;

        new ServerFactory()->createOne([
            'hostname' => $hostname,
            'type' => ServerType::DIRECTADMIN_MAIL,
        ]);

        $this->app->extend(BehavesAsDirectAdmin::class, function () {
            $daMock = self::createMock(DirectAdmin::class);
            $daApiMock = self::createMock(DirectAdminApi::class);
            $daMock->expects(self::once())->method('useServer')->willReturn($daApiMock);
            $daApiMock->expects(self::once())->method('loginAs')->willReturn($daApiMock);
            $daApiMock
                ->expects(self::once())
                ->method('call')
                ->willThrowException(new DirectAdminResponseException('Error'));

            return $daMock;
        });

        $service = self::resolve(MailManagementDirectAdminService::class);
        $this->expectException(MailOnlyException::class);
        $service->deleteUser($hostname, $domain, $domainUser, $mailPopImapUser);
    }
}
