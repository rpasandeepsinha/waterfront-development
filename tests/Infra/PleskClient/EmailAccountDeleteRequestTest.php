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
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteRequest;
use Waterfront\Infra\PleskClient\Messages\EmailAccountDelete\EmailAccountDeleteResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(EmailAccountDeleteResponse::class)]
#[CoversClass(EmailAccountDeleteRequest::class)]
#[CoversClass(HostingPackageClient::class)]
class EmailAccountDeleteRequestTest extends TestCase
{
    #[Test]
    public function deleteEmailAccountSuccess(): void
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
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_delete_account_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_delete_account_response.xml'));
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

        $result = $hostingClient->deleteEmailAccount(
            domain: $domain,
            emailAccount: $emailAccount,
        );

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame('info', $result->mailName);
    }
}
