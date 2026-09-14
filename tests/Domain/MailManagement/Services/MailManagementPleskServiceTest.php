<?php

declare(strict_types=1);

namespace Tests\Domain\MailManagement\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Plesk\Services\PleskHostingService;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\MailManagement\Services\MailManagementPleskService;
use Waterfront\Infra\PleskClient\DTO\MailAccount;

#[CoversClass(MailManagementPleskService::class)]
class MailManagementPleskServiceTest extends IntegrationTestCase
{
    #[Test]
    public function listDomains(): void
    {
        $hostname = 'plesk-server.nl';
        $testDomain = 'get-my-mail-accounts.nl';
        $server = ServerFactory::new()->plesk()->createOne();

        $infoMail = new MailAccount(
            mailName: 'info',
            mailboxEnabled: true,
            mailboxUsage: 10,
            forwarding: false,
            forwardDestinationAddresses: null,
        );

        $forwardMail = new MailAccount(
            mailName: 'forward',
            mailboxEnabled: false,
            mailboxUsage: 0,
            forwarding: true,
            forwardDestinationAddresses: ['test@tester.nl'],
        );

        $serverRepoMock = self::mock(ServerRepository::class);
        $serverRepoMock->shouldReceive('findByHostname')->with($hostname)->andReturn($server);

        $hostingServiceMock = self::mock(PleskHostingService::class);
        $hostingServiceMock
            ->shouldReceive('getMailAccounts')
            ->with($server, $testDomain)
            ->andReturn([
                $infoMail,
                $forwardMail,
            ]);

        $this->app->bind(PleskHostingService::class, fn () => $hostingServiceMock);
        $this->app->bind(ServerRepository::class, fn () => $serverRepoMock);

        $service = $this->app->make(MailManagementPleskService::class);
        $results = $service->listDomain($hostname, $testDomain, 'unused-for-plesk');

        self::assertSame($testDomain, $results['domain']);
        self::assertIsArray($results['users']);
        self::assertCount(1, $results['users']); // Only 'info' should be returned, not the forwarding account
        self::assertSame('info', $results['users'][0]);
    }
}
