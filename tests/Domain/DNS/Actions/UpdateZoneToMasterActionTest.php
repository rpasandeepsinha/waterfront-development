<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Actions;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Actions\DNS\UpdateZoneToMasterAction;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;

#[CoversClass(UpdateZoneToMasterAction::class)]
class UpdateZoneToMasterActionTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string ZONE_NAME = 'test-zone.testing';

    #[Test]
    public function updateZoneToMasterSuccessful(): void
    {
        $pdns = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::ZONE_NAME)
            ),
            new Response(
                204,
            ),
            new Response(
                204,
            ),
        ], static function (RequestInterface $request): void {
            if ($request->getMethod() !== 'PUT') {
                return;
            }

            /** @var array<string> $mastersPayload */
            $mastersPayload = json_decode($request->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame(
                [
                    'kind' => PowerDnsZoneKind::MASTER->value,
                    'last_check' => 0,
                    'masters' => [],
                ],
                $mastersPayload,
                'PDNS zone to master failed'
            );
        });

        $this->pdns($pdns);

        $action = self::resolve(UpdateZoneToMasterAction::class);
        $action->execute(self::ZONE_NAME, 1, 'abc-abc');
    }
}
