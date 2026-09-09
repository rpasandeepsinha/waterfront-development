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
use Waterfront\Infra\PleskClient\Fakers\PleskClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\PleskClient;

#[CoversClass(PleskClient::class)]
class CertificateSelectTest extends IntegrationTestCase
{
    #[Test]
    public function certificateSelectGeneratesCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_certificate_select_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame((string) file_get_contents(__DIR__ . '/data/plesk_certificate_select_request.xml'), (string) $request->getBody());
            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new PleskClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection
        );

        $data = require __DIR__ . '/data/pleskSelectCertificateData.php';
        $result = $customerClient->selectCertificate($data['domain'], $data['name']);

        self::assertSame(Result::STATUS_OK, $result);
    }

    #[Test]
    public function fakeCertificateSelectWillReturnSuccess(): void
    {
        $pleskClient = self::resolve(PleskClientFaker::class);
        $pleskClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskSelectCertificateData.php';
        $status = $pleskClient->selectCertificate($data['domain'], $data['name']);

        self::assertSame(Result::STATUS_OK, $status);
    }
}
