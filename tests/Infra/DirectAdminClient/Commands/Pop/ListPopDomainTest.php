<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\Pop;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\Pop\ListPopDomain;

#[CoversClass(ListPopDomain::class)]
class ListPopDomainTest extends DirectAdminTestCase
{
    #[Test]
    public function testResponseReceivedWithNumericKeysCanBeParsed(): void
    {
        $users = ['user1', 'user2'];
        $response = new Response(200, [], json_encode($users, JSON_THROW_ON_ERROR));

        $command = new ListPopDomain();
        $command->parseResponse($response);

        self::assertSame($users, $command->getUsers());
    }
}
