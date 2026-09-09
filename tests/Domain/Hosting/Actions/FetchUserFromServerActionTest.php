<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Actions\FetchUserFromServerAction;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Interfaces\EmailForwardInterface;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(FetchUserFromServerAction::class)]
#[AllowMockObjectsWithoutExpectations]
class FetchUserFromServerActionTest extends TestCase
{
    private HostingService&MockObject $hostingService;

    private GetSsoUrlAction&MockObject $getSsoUrlAction;

    private MailManagementService&MockObject $mailManagementService;

    private LoggerInterface&MockObject $logger;

    private FetchUserFromServerAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostingService = self::createMock(HostingService::class);
        $this->getSsoUrlAction = self::createMock(GetSsoUrlAction::class);
        $this->mailManagementService = self::createMock(MailManagementService::class);
        $this->logger = self::createMock(LoggerInterface::class);

        $this->action = new FetchUserFromServerAction(
            $this->hostingService,
            $this->getSsoUrlAction,
            $this->mailManagementService,
            $this->logger,
        );
    }

    #[Test]
    public function itGathersEveryLookupForADirectAdminServer(): void
    {
        $server = $this->server(ServerType::DIRECTADMIN);

        $this->hostingService->expects(self::once())
            ->method('getUserConfigAsAdmin')
            ->with(ProviderSlug::DIRECTADMIN->value, 'user-1', $server)
            ->willReturn(['package' => 'basic']);

        $this->getSsoUrlAction->expects(self::once())
            ->method('execute')
            ->with($server, 'user-1', '10.0.0.1')
            ->willReturn('https://panel.example.com/sso');

        $this->mailManagementService->expects(self::once())
            ->method('getEmailForwards')
            ->willReturn([$this->emailForward('info', ['a@x.nl', 'b@x.nl'])]);

        $this->mailManagementService->expects(self::once())
            ->method('getEmailUsersRaw')
            ->willReturn(['users' => ['admin', 'postmaster']]);

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', 'example.com');

        self::assertSame(['package' => 'basic'], $result->userData);
        self::assertSame('https://panel.example.com/sso', $result->ssoUrl);
        self::assertSame(
            [['source' => 'info@example.com', 'destinations' => ['a@x.nl', 'b@x.nl']]],
            $result->mailForwards
        );
        self::assertSame(['admin', 'postmaster'], $result->mailUsers);
        self::assertSame([], $result->errors);
    }

    #[Test]
    public function aFailingLookupIsRecordedAndTheOthersStillReturn(): void
    {
        $server = $this->server(ServerType::DIRECTADMIN);

        $this->hostingService->method('getUserConfigAsAdmin')
            ->willThrowException(new RuntimeException('panel unreachable', 503));

        $this->logger->expects(self::once())->method('warning');

        $this->getSsoUrlAction->method('execute')->willReturn('https://panel.example.com/sso');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        // The failure must not hide the lookup that did succeed.
        self::assertSame('https://panel.example.com/sso', $result->ssoUrl);
        self::assertSame([], $result->userData);
        self::assertCount(1, $result->errors);
        self::assertSame('panel unreachable', $result->errors[0]['message']);
        self::assertSame(503, $result->errors[0]['code']);
    }

    #[Test]
    public function mailIsSkippedWithoutADomain(): void
    {
        $server = $this->server(ServerType::DIRECTADMIN);

        $this->hostingService->method('getUserConfigAsAdmin')->willReturn([]);
        $this->getSsoUrlAction->method('execute')->willReturn('https://panel.example.com/sso');

        $this->mailManagementService->expects(self::never())->method('getEmailForwards');
        $this->mailManagementService->expects(self::never())->method('getEmailUsersRaw');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        self::assertSame([], $result->mailForwards);
        self::assertSame([], $result->mailUsers);
    }

    #[Test]
    public function itStripsThePleskPasswordFromTheResponse(): void
    {
        $server = $this->server(ServerType::PLESK);

        $this->hostingService->method('getUserConfigAsAdmin')->willReturn([
            'response_result' => '<gen_info><password>hunter2</password></gen_info>',
        ]);
        $this->getSsoUrlAction->method('execute')->willReturn('https://plesk.example.com/sso');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        self::assertSame(['response_result' => '<gen_info></gen_info>'], $result->userData);
        self::assertStringNotContainsString('hunter2', json_encode($result->userData, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itStripsEveryPasswordWithoutEatingTheFieldsBetweenThem(): void
    {
        $server = $this->server(ServerType::PLESK);

        $this->hostingService->method('getUserConfigAsAdmin')->willReturn([
            'response_result' => '<password>secret1</password><login>bob</login><password>secret2</password>',
        ]);
        $this->getSsoUrlAction->method('execute')->willReturn('https://plesk.example.com/sso');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        self::assertSame(['response_result' => '<login>bob</login>'], $result->userData);
        $encoded = json_encode($result->userData, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret1', $encoded);
        self::assertStringNotContainsString('secret2', $encoded);
    }

    #[Test]
    public function itStripsAPasswordThatSpansMultipleLines(): void
    {
        $server = $this->server(ServerType::PLESK);

        $this->hostingService->method('getUserConfigAsAdmin')->willReturn([
            'response_result' => "<gen_info>\n  <password>\n    hunter2\n  </password>\n</gen_info>",
        ]);
        $this->getSsoUrlAction->method('execute')->willReturn('https://plesk.example.com/sso');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        self::assertStringNotContainsString('hunter2', json_encode($result->userData, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itStripsTheNestedPleskPasswordKey(): void
    {
        $server = $this->server(ServerType::PLESK);

        $this->hostingService->method('getUserConfigAsAdmin')->willReturn([
            'response_body' => ['customer' => ['get' => ['result' => ['data' => ['gen_info' => [
                'password' => 'hunter2',
                'name' => 'bob',
            ]]]]]],
        ]);
        $this->getSsoUrlAction->method('execute')->willReturn('https://plesk.example.com/sso');

        $result = $this->action->execute($server, 'user-1', '10.0.0.1', null);

        self::assertStringNotContainsString('hunter2', json_encode($result->userData, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('bob', json_encode($result->userData, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function anUnsupportedServerTypeIsRejected(): void
    {
        $server = $this->server(ServerType::SITEBUILDER);

        $this->expectException(UnexpectedValueException::class);

        $this->action->execute($server, 'user-1', '10.0.0.1', null);
    }

    #[Test]
    public function supportsOnlyTheServerTypesWithADriver(): void
    {
        self::assertTrue(FetchUserFromServerAction::supports(ServerType::PLESK));
        self::assertTrue(FetchUserFromServerAction::supports(ServerType::DIRECTADMIN));
        self::assertTrue(FetchUserFromServerAction::supports(ServerType::DIRECTADMIN_MAIL));
        self::assertFalse(FetchUserFromServerAction::supports(ServerType::SITEBUILDER));
    }

    private function server(ServerType $type): Server
    {
        $server = new Server();
        $server->id = 1;
        $server->hostname = 'server-1.example.com';
        $server->type = $type;

        return $server;
    }

    /**
     * @param array<int, string> $destinations
     */
    private function emailForward(string $source, array $destinations): EmailForwardInterface&MockObject
    {
        $emailForward = self::createMock(EmailForwardInterface::class);
        $emailForward->method('getSource')->willReturn($source);
        $emailForward->method('getDestinations')->willReturn($destinations);

        return $emailForward;
    }
}
