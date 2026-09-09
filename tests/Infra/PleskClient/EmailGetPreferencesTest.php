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
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\EmailGetAccountSettings\Parameters as EmailGetAccountSettingsParameters;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Request as EmailGetPrefRequest;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Response as EmailGetPrefResponse;
use Waterfront\Infra\PleskClient\Messages\EmailGetPreferences\Result as EmailGetPrefResult;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(EmailGetPrefResponse::class)]
#[CoversClass(EmailGetPrefRequest::class)]
#[CoversClass(EmailGetPrefResult::class)]
class EmailGetPreferencesTest extends IntegrationTestCase
{
    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_get_preferences_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_get_preferences_request.xml'), (string) $request->getBody());
            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskEmailGetAccountSettingsData.php';
        $parameters = EmailGetAccountSettingsParameters::create($data);

        $result = $customerClient->getPreferencesEmail($parameters);

        self::assertSame(EmailGetPrefResult::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function getEmailPreferencesResult(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $data = require __DIR__ . '/data/pleskEmailGetAccountSettingsData.php';
        $parameters = EmailGetAccountSettingsParameters::create($data);
        $result = $hostingPackageClient->getPreferencesEmail($parameters);

        self::assertTrue($result->isCatchAllSet());
        self::assertSame('forwarded@catchall.nl', $result->getCatchAllForward());

        self::assertTrue($result->spamProtectSignEnabled);
        self::assertTrue($result->mailService);
        self::assertSame('Default Certificate', $result->webmailCertificate);
        self::assertSame('none', $result->webmail);
    }
}
