<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetRequest;
use Waterfront\Infra\PleskClient\Messages\EmailPasswordReset\EmailPasswordResetResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(EmailPasswordResetResponse::class)]
#[CoversClass(EmailPasswordResetRequest::class)]
#[CoversClass(HostingPackageClient::class)]
class EmailPasswordResetTest extends TestCase
{
    #[Test]
    public function emailPasswordResetRequest(): void
    {
        // Initial data that matches XML response
        $domain = 'test-domain.com';
        $pleskMailName = 'info';
        $emailAccount = $pleskMailName . '@test-domain.com';

        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_response.xml'));
            },
            function (RequestInterface $request) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_reset_password_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_reset_password_response.xml'));
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');

        $hostingClient = new HostingPackageClient(
            client: $client,
            configuration: $this->app->make(ConfigurationInterface::class),
            logger: $this->app->make(LoggerInterface::class),
            connection: $connection
        );

        $result = $hostingClient->resetEmailPassword(
            domain: $domain,
            emailAccount: $emailAccount,
            password: 'new-password'
        );

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame('info', $result->mailName);
    }
}
