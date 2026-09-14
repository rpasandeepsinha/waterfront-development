<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\DTO\ModifyParameters;
use Waterfront\Infra\OpenproviderClient\Exceptions\OpenProviderResultException;
use Waterfront\Infra\OpenproviderClient\Fakers\OpenproviderClientFaker;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\NameServer;
use Waterfront\Infra\OpenproviderClient\Messages\NameServerCollection;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsSecKey;

#[CoversClass(OpenproviderClient::class)]
class DomainModifyTest extends IntegrationTestCase
{
    /** @return array<mixed> */
    public static function validDataProvider(): array
    {
        $nameServers = new NameServerCollection(
            new NameServer('ns03.testing.test', '1.2.3.3'),
            new NameServer('ns04.testing.test', '1.2.3.4'),
            new NameServer('ns05.testing.test', '1.2.3.5'),
        );

        $dnssecKey = include __DIR__ . '/data/dnsseckey.php';

        return [
            'setNameServers' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'nameServers' => $nameServers->toArray(),
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_set_nameservers.xml',
                ),
            ],
            'disableAutoRenew' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'autoRenew' => false,
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_disable_autorenew.xml',
                ),
            ],
            'enableAutoRenew' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'autoRenew' => true,
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_enable_autorenew.xml',
                ),
            ],
            'enableDnssec' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'dnssecKeys' => [
                        PowerDnsSecKey::fromArray($dnssecKey),
                    ],
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_enable_dnssec.xml',
                ),
            ],
            'disableDnssec' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'dnssecKeys' => [],
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_disable_dnssec.xml',
                ),
            ],
            'enablePrivateWhois' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'isPrivateWhoisEnabled' => true,
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_private_enable.xml',
                ),
            ],
            'disablePrivateWhois' => [
                'parameters' => ModifyParameters::create([
                    'domain' => 'example.org',
                    'isPrivateWhoisEnabled' => false,
                ]),
                'requestBody' => file_get_contents(
                    __DIR__ . '/data/openprovider_modify_request_private_disable.xml',
                ),
            ],
        ];
    }

    #[DataProvider('validDataProvider')]
    #[Test]
    public function createXml(ModifyParameters $parameters, string $requestBody): void
    {
        $client = new OpenproviderClientFaker(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            $this->createStub(Dispatcher::class),
        );

        $request = $client->getModifyDomainRequest($parameters);

        self::assertXmlStringEqualsXmlString(
            $requestBody,
            $request->getXml(),
            ' - domain modify xml created correctly',
        );
    }

    #[DataProvider('validDataProvider')]
    #[Test]
    public function successfulCall(ModifyParameters $parameters, ?string $requestBody): void
    {
        $client = self::resolve(OpenproviderClient::class);
        $client->modifyDomain($parameters);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('validDataProvider')]
    #[Test]
    public function failedCall(ModifyParameters $parameters, ?string $requestBody): void
    {
        $this->expectException(OpenProviderResultException::class);

        $client = self::resolve(OpenproviderClient::class);
        self::assertInstanceOf(OpenproviderClientFaker::class, $client);
        $client->setDesiredResponseCode(500);

        $client->modifyDomain($parameters);
    }
}
