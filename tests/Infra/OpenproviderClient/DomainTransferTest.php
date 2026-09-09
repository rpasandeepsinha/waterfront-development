<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\TransferParameters;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\DomainTransferResponse;
use Waterfront\Infra\OpenproviderClient\Messages\NameServer;
use Waterfront\Infra\OpenproviderClient\Messages\NameServerCollection;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

#[CoversClass(OpenproviderClient::class)]
class DomainTransferTest extends IntegrationTestCase
{
    #[Test]
    public function domainTransferService(): void
    {
        $domainTransferClient = self::resolve(OpenproviderClient::class);

        $result = $domainTransferClient->transferDomain($this->getParameters());

        self::assertSame(DomainStatus::REQUESTED->value, $result->getStatus(), ' - status is valid');
    }

    #[Test]
    public function createXmlWithNameServers(): void
    {
        $domainTransferClient = new OpenproviderClientFaker(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            $this->createStub(Dispatcher::class),
        );

        $parameters = $this->getParameters();
        $handles = $domainTransferClient->createHandles($parameters->getCustomer());

        $request = $domainTransferClient->getTransferDomainRequest($parameters, $handles);
        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_transfer_request_nameservers.xml');

        self::assertXmlStringEqualsXmlString((string) $requestXml, $request->getXml());
    }

    #[Test]
    public function createXmlWithoutNameServers(): void
    {
        $domainTransferClient = new OpenproviderClientFaker(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            $this->createStub(Dispatcher::class),
        );

        $parameters = $this->getParameters(false);
        $handles = $domainTransferClient->createHandles($parameters->getCustomer());

        $request = $domainTransferClient->getTransferDomainRequest($parameters, $handles);
        $requestXml = file_get_contents(__DIR__ . '/data/openprovider_transfer_request_nsgroup.xml');

        self::assertXmlStringEqualsXmlString((string) $requestXml, $request->getXml());
    }

    #[Test]
    public function processResponseXml(): void
    {
        $httpResponse = new Response(
            200,
            ['Content-Type' => 'text/xml'],
            (string) file_get_contents(__DIR__ . '/data/openprovider_transfer_response.xml')
        );

        $clientResponse = new DomainTransferResponse($httpResponse);
        $result = $clientResponse->getResult();
        $status = $result->getStatus();

        self::assertSame($status, DomainStatus::REQUESTED->value);
        self::assertSame('asdf1234', $result->getTransferSecret());
        self::assertSame('2020-07-03 11:29:53', $result->getExpirationDate());
        self::assertSame('2020-07-03 11:29:53', $result->getRenewalDate());
    }

    /**
     * Prepare a parameters object for the domain transfer.
     */
    private function getParameters(bool $withNameServers = true): TransferParameters
    {
        $customer = include(__DIR__ . '/data/customer.php');
        $dnssecKey = include(__DIR__ . '/data/dnsseckey.php');

        $data = [
            'domain'          => 'example.org',
            'customer'        => $customer,
            'partner'         => $customer,
            'period'          => 1,
            'nameServerGroup' => 'test_nameservergroup',
            'transferSecret'  => 'asdf1234',
            'dnssecKey'       => PowerDnsSecKey::fromArray($dnssecKey),
        ];

        if ($withNameServers) {
            $data['nameServers'] = new NameServerCollection(
                new NameServer('ns03.testing.test', '1.2.3.3'),
                new NameServer('ns04.testing.test', '1.2.3.4'),
                new NameServer('ns05.testing.test', '1.2.3.5'),
            );
        }

        return TransferParameters::create($data);
    }
}
