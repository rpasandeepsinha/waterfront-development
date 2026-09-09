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
use Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings\Result;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class EmailGetAccountSettingsTest extends IntegrationTestCase
{
    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_get_account_settings_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_get_account_settings_response.xml'));
            },
            function (RequestInterface $request) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_email_get_preferences_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_email_get_preferences_response.xml'));
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

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

        $result = $customerClient->getExistingEmailAccounts($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function getExistingEmailAccounts(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $data = require __DIR__ . '/data/pleskEmailGetAccountSettingsData.php';
        $parameters = EmailGetAccountSettingsParameters::create($data);

        $result = $hostingPackageClient->getExistingEmailAccounts($parameters);
        $emailAccounts = $result->getEmailAccounts();

        self::assertSame('info', $emailAccounts[0]->getMailName());
        /** @var array<int,string> $forwardAddresses */
        $forwardAddresses = $emailAccounts[0]->getForwardDestinationAddresses();
        self::assertSame('forwarded@info.nl', $forwardAddresses[0]);

        self::assertSame('testy', $emailAccounts[1]->getMailName());
        /** @var array<int,string> $forwardAddresses */
        $forwardAddresses = $emailAccounts[1]->getForwardDestinationAddresses();
        self::assertSame('forwarded@testy.nl', $forwardAddresses[0]);
    }
}
