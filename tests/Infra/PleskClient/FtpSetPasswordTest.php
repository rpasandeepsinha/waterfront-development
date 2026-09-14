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
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\FtpSetPassword\FtpSetPasswordRequest;
use Waterfront\Infra\PleskClient\Messages\FtpSetPassword\FtpSetPasswordResponse;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(FtpSetPasswordResponse::class)]
#[CoversClass(FtpSetPasswordRequest::class)]
class FtpSetPasswordTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'test-domain.nl'; // matches XML data
    private const string USER = 'user'; // matches XML data
    private const string PASSWORD = 'secret'; // matches XML data

    #[Test]
    public function setFtpPasswordSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_ftp_set_password_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_ftp_set_password_response.xml'),
                );
            },
        ]);

        $result = $this->setupMockClient($mock)->setFtpPassword(self::TEST_DOMAIN, self::USER, self::PASSWORD);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function setFtpPasswordHandlesErrorResponse(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_ftp_set_password_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_ftp_set_password_fail_response.xml'),
                );
            },
        ]);

        $result = $this->setupMockClient($mock)->setFtpPassword(self::TEST_DOMAIN, self::USER, self::PASSWORD);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
        self::assertSame(
            'The password should be between 5 to 255 characters in length, and it should not contain the username.',
            $result->getErrorMessage(),
        );
    }

    private function setupMockClient(MockHandler $mockHandler): HostingPackageClient
    {
        $handlerStack = HandlerStack::create($mockHandler);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');

        return new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );
    }
}
