<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient;

use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OutOfBoundsException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;
use Waterfront\Infra\PowerDnsClient\PowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Services\PowerDnsZoneToDnsZoneConverter;

trait PowerDnsMockHelper
{
    /**
     * @param array<array<string, mixed>> $customRrsets
     */
    private function getMockedZoneResponseBodyWithoutRRSIGAndDNSKEYRecords(
        string $domain,
        array $customRrsets = [],
        string $kind = 'Master',
    ): string {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '1.2.3.4',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'A',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => 'txt-content-goes-here',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'TXT',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "a.misconfigured.powerdns.server. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        if ($customRrsets !== []) {
            $rrsets = array_merge($rrsets, $customRrsets);
        }

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => $kind,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array<string, mixed>> $customRrsets
     */
    private static function getStaticMockedZoneResponseBodyWithoutRRSIGAndDNSKEYRecords(
        string $domain,
        array $customRrsets = [],
        string $kind = 'Master',
        string $nameserver = 'a.misconfigured.powerdns.server',
    ): string {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '1.2.3.4',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'A',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => 'txt-content-goes-here',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'TXT',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "$nameserver. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        if ($customRrsets !== []) {
            $rrsets = array_merge($rrsets, $customRrsets);
        }

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => $kind,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array<string, mixed>> $customRrsets
     */
    private function getMockedZoneResponseBodyWithNsRecords(string $domain, array $customRrsets = []): string
    {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => 'test-ns1.sandwaveio.testing.',
                        'disabled' => false,
                    ],
                    [
                        'content' => 'test-ns2.sandwaveio.testing.',
                        'disabled' => false,
                    ],
                    [
                        'content' => 'test-ns3.sandwaveio.testing.',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'NS',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "a.misconfigured.powerdns.server. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        if ($customRrsets !== []) {
            $rrsets = array_merge($rrsets, $customRrsets);
        }

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => 'Master',
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    private function getMockedKeyResponseBody(): string
    {
        $response = json_encode([
            [
                'active' => true,
                'algorithm' => 'ECDSAP256SHA256',
                'bits' => 256,
                'dnskey' => '257 3 13 kJugvFdAwIy1cLirD3H23rJuf8Ul1XFwponZ7y8qq7rMBN3/Hdvs9PnRTD6Hm4R8sANAh5Cqfn2EZcXvROIxLw==',
                'ds' => [
                    '44430 13 1 2d732d72778cf2e5948651e9a200131045378ccc',
                    '44430 13 2 90dec6761b4d0fba3cb9f50423172b07e1391a421af5beab4b3829c2ccf827b1',
                    '44430 13 4 1bfb7fd945bd669778a44047f5bfbacecf0a3ff95380c7a2483fc96d8fa4e1c22b7a3bc6825a28bbb3aec189e8a9b4f5',
                ],
                'flags' => 257,
                'id' => 158513,
                'keytype' => 'csk',
                'type' => 'Cryptokey',
            ],
            [
                'active' => false,
                'algorithm' => 'RSASHA256',
                'bits' => 1024,
                'dnskey' => '257 3 8 AwEAAct8h9u4jebS7xSbkmXWtIYMIjdZ8W/8UpUqrrLKd/Qd+ty0idXTlbfEWZ7e2Lop7RFQ3CkOaX3LMcSn8PRNEw5i5ThanQpYSZxpoTRKnDboVPa5Kl93RRdgcpuzaZADs6rHL14l+shruJkEBjS4xTI3cW6Z8GFuondMPW1I91kt',
                'ds' => [
                    '62686 8 1 17b54e0f857b9883cdac3c710fec955ed9fe9929',
                    '62686 8 2 2ac8c798f552821258f4206ea1646104f285e35d6912ed2ed7e126525d9ccc4b',
                    '62686 8 4 502eaa756c5747e9cfc1904864120e55c81b7cb3a20dab82ddaa9da912d2323f0db57afe31eb947bed0de1d99dc369da',
                ],
                'flags' => 257,
                'id' => 158514,
                'keytype' => 'csk',
                'type' => 'Cryptokey',
            ],
        ]);

        assert(is_string($response));

        return $response;
    }

    private function makePdns(int $responseCode, string $responseBody, ?callable $assertClosure = null): PowerDnsClient
    {
        return new PowerDnsClient(
            $this->makeInternalGuzzleClient([new Response($responseCode, [], $responseBody)], $assertClosure),
            self::resolve(PowerDnsZoneToDnsZoneConverter::class),
            self::resolve(LoggerInterface::class),
        );
    }

    /**
     * @param Response[] $responses
     */
    private function makePdnsWithMultipleResponses(array $responses, ?callable $assertClosure = null): PowerDnsClient
    {
        return new PowerDnsClient(
            $this->makeInternalGuzzleClient($responses, $assertClosure),
            self::resolve(PowerDnsZoneToDnsZoneConverter::class),
            self::resolve(LoggerInterface::class),
        );
    }

    /**
     * @param array<array<string, mixed>> $rrsets
     */
    private static function getMockedZoneResponseBodyWithRrsets(
        string $domain,
        array $rrsets = [],
        string $kind = 'Master',
    ): string {
        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => $kind,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array<string, mixed>> $customRrsets
     */
    private function getMockedZoneResponseBody(
        string $domain,
        array $customRrsets = [],
        string $kind = 'Master',
    ): string {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '1.2.3.4',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'A',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => 'txt-content-goes-here',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'TXT',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "A 8 2 900 20230323000000 20230309000000 1629 $domain.",
                        'disabled' => false,
                    ],
                    [
                        'content' => "TXT 8 2 900 20230323000000 20230309000000 1629 $domain.",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'RRSIG',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '256 3 8 hashgoeshere1',
                        'disabled' => false,
                    ],
                    [
                        'content' => '257 3 8 hashgoeshere2',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'DNSKEY',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "a.misconfigured.powerdns.server. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        if ($customRrsets !== []) {
            $rrsets = array_merge($rrsets, $customRrsets);
        }

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => $kind,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    private function getMockedZoneResponseBodyForRedirects(
        string $domain,
        string $redirectNameForARrset,
        string $redirectContentForARrset,
        string $redirectNameForAAAARrset,
        string $redirectContentForAAAARrset,
    ): string {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$redirectNameForARrset.",
                'records' => [
                    [
                        'content' => $redirectContentForARrset,
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'A',
            ],
            [
                'comments' => [],
                'name' => "$redirectNameForAAAARrset.",
                'records' => [
                    [
                        'content' => $redirectContentForAAAARrset,
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'AAAA',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "a.misconfigured.powerdns.server. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => PowerDnsZoneKind::MASTER->value,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array<string, mixed>> $customRrsets
     */
    private static function getStaticMockedZoneResponseBody(
        string $domain,
        array $customRrsets = [],
        string $kind = 'Master',
        string $nameserver = 'a.misconfigured.powerdns.server',
    ): string {
        $rrsets = [
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '1.2.3.4',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'A',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => 'txt-content-goes-here',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'TXT',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "A 8 2 900 20230323000000 20230309000000 1629 $domain.",
                        'disabled' => false,
                    ],
                    [
                        'content' => "TXT 8 2 900 20230323000000 20230309000000 1629 $domain.",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'RRSIG',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => '256 3 8 hashgoeshere1',
                        'disabled' => false,
                    ],
                    [
                        'content' => '257 3 8 hashgoeshere2',
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'DNSKEY',
            ],
            [
                'comments' => [],
                'name' => "$domain.",
                'records' => [
                    [
                        'content' => "$nameserver. hostmaster.$domain. 2022050502 10800 3600 604800 3600",
                        'disabled' => false,
                    ],
                ],
                'ttl' => 3600,
                'type' => 'SOA',
            ],
        ];

        if ($customRrsets !== []) {
            $rrsets = array_merge($rrsets, $customRrsets);
        }

        return json_encode([
            'account' => '',
            'api_rectify' => false,
            'dnssec' => true,
            'edited_serial' => 2_022_050_502,
            'id' => "$domain.",
            'kind' => $kind,
            'last_check' => 0,
            'master_tsig_key_ids' => [],
            'masters' => [],
            'name' => "$domain.",
            'notified_serial' => 0,
            'nsec3narrow' => false,
            'nsec3param' => '',
            'rrsets' => $rrsets,
            'serial' => 2_022_050_502,
            'slave_tsig_key_ids' => [],
            'soa_edit' => '',
            'soa_edit_api' => 'DEFAULT',
            'url' => "/api/v1/servers/localhost/zones/$domain.",
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, array<mixed>> $metadata
     */
    private static function getStaticMockedMetadataResponseBody(array $metadata): string
    {
        $responseBody = [];

        foreach ($metadata as $kind => $metadataValue) {
            $responseBody[] = [
                'kind' => $kind,
                'metadata' => $metadataValue,
                'type' => 'Metadata',
            ];
        }

        return json_encode($responseBody, JSON_THROW_ON_ERROR);
    }

    /**
     * @param Response[] $responses
     */
    private function makeInternalGuzzleClient(array $responses, ?callable $assertClosure = null): InternalPowerDnsClient
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));

        if ($assertClosure !== null) {
            $handlerStack->push(fn (callable $handler): Closure => function (RequestInterface $request, $options) use (
                $handler,
                $assertClosure,
            ) {
                $assertClosure($request);

                return $handler($request, $options);
            });
        }

        return new InternalPowerDnsClient(new Client(['handler' => $handlerStack, 'http_errors' => false]));
    }

    /**
     * @param array<string, callable> $responses
     */
    private function makePdnsWithResponseCallback(array $responses, ?callable $assertClosure = null): PowerDnsClient
    {
        return new PowerDnsClient(
            $this->makeInternalGuzzleClientWithResponseCallback($responses, $assertClosure),
            self::resolve(PowerDnsZoneToDnsZoneConverter::class),
            self::resolve(LoggerInterface::class),
        );
    }

    /**
     * @param array<string, callable> $responses
     */
    private function makeInternalGuzzleClientWithResponseCallback(
        array $responses,
        ?callable $assertClosure = null,
    ): InternalPowerDnsClient {
        $handlerStack = HandlerStack::create(new MockHandler());

        if ($assertClosure !== null) {
            $handlerStack->push(
                fn (callable $handler): Closure => function (RequestInterface $request, $options) use (
                    $handler,
                    $assertClosure,
                ) {
                    $assertClosure($request);

                    return $handler($request, $options);
                },
            );
        }

        $handlerStack->push(
            fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $responses) {
                assert($handler instanceof MockHandler);
                $handler->append(new Response()); // trick it into thinking the queue isn't empty

                $promise = $handler($request, $options);

                $uri = $request->getUri()->getHost();
                if ($request->getUri()->getPort() !== null) {
                    $uri .= ':' . $request->getUri()->getPort();
                }

                $uri .= $request->getUri()->getPath();

                if (! array_key_exists($uri, $responses)) {
                    throw new OutOfBoundsException(sprintf(
                        'URL not found in mocked responses: %s',
                        $uri,
                    ));
                }

                return $promise->then(
                    fn (): ResponseInterface => $responses[$uri]($request),
                );
            },
        );

        $config = self::resolve(ConfigurationInterface::class);

        return new InternalPowerDnsClient(
            new Client([
                'base_uri' => $config->getAsString('powerdnsclient.connection.api_url'),
                'handler' => $handlerStack,
                'http_errors' => false,
            ]),
        );
    }
}
