<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient\Commands\EmailForwards;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\DirectAdminTestCase;
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\DeleteEmailForward;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(DeleteEmailForward::class)]
class DeleteEmailForwardTest extends DirectAdminTestCase
{
    #[Test]
    public function deleteEmailForward(): void
    {
        $responseBody = [
            'result' => '',
            'success' => 'Forwarders deleted',
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($responseBody, JSON_THROW_ON_ERROR)),
        ]));

        $command = new DeleteEmailForward();
        $command->setSelect0('test');
        $command->setDomain('test.com');

        $api = new DirectAdminApi(
            $this->getTestServer(),
            new Client(['handler' => $handlerStack]),
        );

        $command = $api->loginAs('fake-user')->call($command);

        self::assertTrue($command->hasSucceeded());
        self::assertSame(
            [
                'result' => '',
                'success' => 'Forwarders deleted',
            ],
            $command->getFormValues(),
        );

        self::assertSame(
            'action=delete&domain=test.com&select0=test',
            $command->getRequest()->getBody()->getContents(),
        );
    }

    #[Test]
    public function deleteEmailForwardsFailed(): void
    {
        $errorResponse = [
            'error' => 'Could not execute your request',
            'result' => 'You do not own that domain',
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($errorResponse, JSON_THROW_ON_ERROR)),
        ]));

        $command = new DeleteEmailForward();
        $command->setSelect0('test.test');
        $command->setDomain('test.com');

        $api = new DirectAdminApi(
            $this->getTestServer(),
            new Client([
                'handler' => $handlerStack,
                // Since we are injecting a custom client we need to ensure the same error handling
                // as the client set through the normal flow.
                'http_errors' => false,
            ]),
        );

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs(
            'Failed [DeleteEmailForward]: Could not execute your request - You do not own that domain',
        );

        $api->loginAs('fake-user')->call($command);
    }
}
