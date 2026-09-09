<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\ExtensionRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

#[CoversClass(OpenproviderClient::class)]
class ExtensionRetrieveTest extends IntegrationTestCase
{
    #[Test]
    public function createXml(): void
    {
        $request = new ExtensionRetrieveRequest(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            'nl'
        );

        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_retrieve_extension_request.xml');

        self::assertXmlStringEqualsXmlString((string) $requestXml, $request->getXml());
    }

    #[Test]
    public function retrieveExtension(): void
    {
        $extensionClient = self::resolve(OpenproviderClient::class);
        $extension = $extensionClient->retrieveExtension('nl');

        self::assertTrue($extension->isDnssecAllowed());
    }

    #[Test]
    public function retrieveExtensionNegative(): void
    {
        $extensionClient = self::resolve(OpenproviderClient::class);

        $this->expectException(OpenProviderResultException::class);
        $this->expectExceptionCode(307);

        $extensionClient->retrieveExtension('eennietbestaandetld');
    }
}
