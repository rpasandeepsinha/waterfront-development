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
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateRequest;
use Waterfront\Infra\PleskClient\Messages\EmailAccountCreate\EmailAccountCreateResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(EmailAccountCreateResponse::class)]
#[CoversClass(EmailAccountCreateRequest::class)]
#[CoversClass(HostingPackageClient::class)]
class EmailAccountCreateRequestTest extends TestCase
{
    #[Test]
    public function createEmailAccountSuccess(): void
    {
        // Initial data that matches XML response
        $domain = 'test-domain.com';
        $siteId = 12345;
        $pleskMailName = 'info';
        $emailAccount = $pleskMailName . '@test-domain.com';

        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_get_hosting_website_response.xml'),
                );
            },
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_email_create_account_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_email_create_account_response.xml'),
                );
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
            connection: $connection,
        );

        $result = $hostingClient->createEmailAccount(
            domain: $domain,
            emailAccount: $emailAccount,
            password: 'test-password',
        );

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame($siteId, (int) $result->getMailId());
        self::assertSame('info', $result->getMailName());
    }
}
