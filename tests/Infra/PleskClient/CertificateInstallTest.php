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
use Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall\Parameters as InstallCertificateParameters;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\PleskClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\PleskClient;

#[CoversClass(PleskClient::class)]
class CertificateInstallTest extends IntegrationTestCase
{
    #[Test]
    public function certificateInstallGeneratesCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_certificate_install_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame(
                (string) file_get_contents(__DIR__ . '/data/plesk_certificate_install_request.xml'),
                (string) $request->getBody(),
            );

            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new PleskClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $data = require __DIR__ . '/data/pleskInstallCertificateData.php';
        $parameters = InstallCertificateParameters::create($data);

        $result = $customerClient->installCertificate($parameters);

        self::assertSame(Result::STATUS_OK, $result);
    }

    #[Test]
    public function fakeCertificateInstallWillReturnSuccess(): void
    {
        $pleskClient = self::resolve(PleskClientFaker::class);
        $pleskClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskInstallCertificateData.php';
        $parameters = InstallCertificateParameters::create($data);
        $status = $pleskClient->installCertificate($parameters);

        self::assertSame(Result::STATUS_OK, $status);
    }
}
