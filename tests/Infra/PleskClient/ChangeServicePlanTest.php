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
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
class ChangeServicePlanTest extends IntegrationTestCase
{
    #[Test]
    public function getHostingSiteSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_serviceplan_get_response.xml')),
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_change_serviceplan_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_change_serviceplan_response.xml'),
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

        /** @var array{domain: string, serviceplan: string} $data */
        $data = require __DIR__ . '/data/pleskChangeServicePlanData.php';
        $domain = $data['domain'];
        $servicePlanGuuid = $data['serviceplan'];

        $result = $customerClient->changeServicePlan($domain, $servicePlanGuuid);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function changeServiceplanSuccess(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $hostingPackageClient->setDesiredResponseCode(200);

        /** @var array{domain: string, serviceplan: string} $data */
        $data = require __DIR__ . '/data/pleskChangeServicePlanData.php';
        $domain = $data['domain'];
        $servicePlanGuuid = $data['serviceplan'];
        $result = $hostingPackageClient->changeServicePlan($domain, $servicePlanGuuid);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function changeServiceplanFailure(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $hostingPackageClient->setDesiredResponseCode(500);

        /** @var array{domain: string, serviceplan: string} $data */
        $data = require __DIR__ . '/data/pleskChangeServicePlanData.php';
        $domain = $data['domain'];
        $servicePlanGuuid = $data['serviceplan'];

        $result = $hostingPackageClient->changeServicePlan($domain, $servicePlanGuuid);

        self::assertSame(500, $result->getErrorCode());
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
