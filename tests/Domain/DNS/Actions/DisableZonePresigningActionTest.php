<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;

#[CoversClass(DisableZonePresigningAction::class)]
class DisableZonePresigningActionTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    #[Test]
    public function removesRRSIGAndDNSKEYRecordsAndDisablesPresigning(): void
    {
        CarbonImmutable::setTestNow('2023-12-11 12:00:00');

        $requestCount = 0;

        $mock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    'sandwave.io',
                    [
                        [
                            'name' => 'sandwave.io.',
                            'type' => 'RRSIG',
                            'comments' => [],
                            'records' => [
                                [
                                    'content' => 'rrsig content',
                                    'disabled' => false,
                                ],
                            ],
                            'ttl' => 3600,
                        ],
                        [
                            'name' => 'sandwave.io.',
                            'type' => 'DNSKEY',
                            'comments' => [],
                            'records' => [
                                [
                                    'content' => 'dnskey content',
                                    'disabled' => false,
                                ],
                            ],
                            'ttl' => 3600,
                        ],
                        [
                            'name' => 'sandwave.io.',
                            'type' => 'A',
                            'comments' => [],
                            'records' => [
                                [
                                    'content' => '1.2.3.4',
                                    'disabled' => false,
                                ],
                            ],
                            'ttl' => 3600,
                        ],
                    ],
                ),
            ),
            new Response(
                204,
                [],
            ),
            new Response(),
            new Response(),
        ], static function (RequestInterface $request) use (&$requestCount): void {
            $requestCount++;

            /*
             * First 2 requests made are for obtaining DNS zone.
             * See CustomerSharedDnsService::applyDiffToZone
             */

            if ($requestCount === 2) {
                /** @var stdClass $data */
                $data = json_decode($request->getBody()->getContents(), null, 512, JSON_THROW_ON_ERROR);
                /** @var array<stdClass> $rrsets */
                $rrsets = $data->rrsets;

                self::assertSame('api/v1/servers/localhost/zones/sandwave.io', $request->getUri()->getPath());
                self::assertSame(Request::METHOD_PATCH, $request->getMethod());
                // 2 for the keys and 1 for the SOA bump.
                self::assertCount(3, $rrsets);

                /*
                 * CustomerSharedDnsService::applyDiffToZone also includes existing records with a "REPLACE" change type.
                 * This "change" doesn't change anything; SOA record still contains same parameters as original
                 */
                $rrsigSet = $rrsets[0];

                self::assertSame('sandwave.io.', $rrsigSet->name);
                self::assertSame('RRSIG', $rrsigSet->type);
                self::assertSame('DELETE', $rrsigSet->changetype);

                $dnsKeySet = $rrsets[1];
                self::assertSame('sandwave.io.', $dnsKeySet->name);
                self::assertSame('DNSKEY', $dnsKeySet->type);
                self::assertSame('DELETE', $dnsKeySet->changetype);

                $soaSet = $rrsets[2];

                self::assertSame('sandwave.io.', $soaSet->name);
                self::assertSame('SOA', $soaSet->type);
                self::assertSame(3600, $soaSet->ttl);
                self::assertSame('REPLACE', $soaSet->changetype);
                self::assertCount(1, $soaSet->records);
                self::assertSame(
                    'a.misconfigured.powerdns.server. hostmaster.sandwave.io. 2023121101 10800 3600 604800 3600',
                    $soaSet->records[0]->content,
                );
                self::assertFalse($soaSet->records[0]->disabled);
            } elseif ($requestCount === 4) {
                self::assertSame(Request::METHOD_GET, $request->getMethod());
                self::assertSame('presigned/sandwave.io', $request->getUri()->getPath());
            }
        });

        $this->pdns($mock);
        self::resolve(DisableZonePresigningAction::class)->disable('sandwave.io');

        self::assertSame(3, $requestCount);
    }
}
