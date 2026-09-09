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
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class EnableTest extends IntegrationTestCase
{
    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, $options) {
                self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_enable_request.xml'), (string) $request->getBody());
                return new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_enable_response.xml'));
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

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);

        $result = $customerClient->enable($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function enableSuccess(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);
        $result = $hostingClient->enable($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function enableFailure(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(500);

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);
        $result = $hostingClient->enable($parameters);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
