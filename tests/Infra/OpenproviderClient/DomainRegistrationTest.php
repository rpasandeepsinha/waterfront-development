<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RegistrationParameters;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRegistrationResponse;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

#[CoversClass(OpenproviderClient::class)]
class DomainRegistrationTest extends IntegrationTestCase
{
    #[Test]
    public function domainRegistrationService(): void
    {
        $this->expectNotToPerformAssertions();

        $client = self::resolve(OpenproviderClient::class);
        $client->registerDomain($this->getParameters());
    }

    #[Test]
    public function createXml(): void
    {
        $client = new OpenproviderClientFaker(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            $this->createStub(Dispatcher::class),
        );

        $parameters = $this->getParameters();
        $handles = $client->createHandles($parameters->getCustomer());

        $request = $client->getRegisterDomainRequest($parameters, $handles);
        $expectedXml = file_get_contents(__DIR__ . '/data/openprovider_register_request.xml');

        self::assertXmlStringEqualsXmlString(
            (string) $expectedXml,
            $request->getXml(),
            'The xml of the domain registration request does not match the expected values.',
        );
    }

    #[Test]
    public function processResponseXml(): void
    {
        $response = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_register_response.xml'),
        );

        $domainRegResponse = new DomainRegistrationResponse($response);
        $result = $domainRegResponse->getResult();
        $status = $result->getStatus();

        self::assertSame(DomainStatus::ACTIVE, $status, ' - retrieve status');
        self::assertSame('2011-04-22 14:14:32', $result->getActivationDate(), ' - retrieve activation date');
        self::assertSame('2012-04-22 14:41:32', $result->getExpirationDate(), ' - retrieve expiration date');
        self::assertSame(
            '2012-04-22 14:41:32',
            $result->getOpenProviderExpirationDate(),
            ' - retrieve open provider expiration date',
        );
        self::assertSame('123456', $result->getAuthCode(), ' - retrieve auth code');
    }

    /**
     * Prepare a parameters object for the domain registration.
     */
    private function getParameters(): RegistrationParameters
    {
        $customer = include __DIR__ . '/data/customer.php';
        $dnssecKey = include __DIR__ . '/data/dnsseckey.php';

        $nameservers = [
            new Nameserver('ns-01.sandwave.io'),
            new Nameserver('ns-02.sandwave.io'),
        ];

        return RegistrationParameters::create([
            'domain' => 'example.org',
            'customer' => $customer,
            'handles' => new Handles('test-handle'),
            'period' => 1,
            'nameServers' => $nameservers,
            'dnssecKeys' => [
                PowerDnsSecKey::fromArray($dnssecKey),
            ],
        ]);
    }
}
