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
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\ChangeHostingPackageStatus\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class DisableTest extends IntegrationTestCase
{
    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request, $options) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_disable_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    HttpResponse::HTTP_OK,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_disable_response.xml'),
                );
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
            connection: $connection,
        );

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);

        $result = $customerClient->disable($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function disableSuccess(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(HttpResponse::HTTP_OK);

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);
        $result = $hostingClient->disable($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function disableFailure(): void
    {
        $hostingClient = self::resolve(HostingPackageClientFaker::class);
        $hostingClient->setDesiredResponseCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR);

        $data = require __DIR__ . '/data/pleskChangeHostingPackageStatusData.php';
        $parameters = Parameters::create($data);
        $result = $hostingClient->disable($parameters);

        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
