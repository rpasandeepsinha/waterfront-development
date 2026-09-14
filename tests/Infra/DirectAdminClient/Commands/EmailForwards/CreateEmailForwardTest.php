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
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\CreateEmailForward;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(CreateEmailForward::class)]
class CreateEmailForwardTest extends DirectAdminTestCase
{
    #[Test]
    public function createEmailForward(): void
    {
        $responseBody = [
            'result' => "Alias source@test.test -> destination1@test.test,destination2@test.test has been created\n",
            'success' => 'Forwarder created',
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($responseBody, JSON_THROW_ON_ERROR)),
        ]));

        $command = new CreateEmailForward();
        $command->setDomain('test.test');
        $command->setUser('source');
        $command->setEmail(['destination1@test.test', 'destination2@test.test']);

        $api = new DirectAdminApi(
            $this->getTestServer(),
            new Client(['handler' => $handlerStack]),
        );

        $command = $api->loginAs('fake-user')->call($command);

        self::assertTrue($command->hasSucceeded());
        self::assertSame(
            [
                'result' => "Alias source@test.test -> destination1@test.test,destination2@test.test has been created\n",
                'success' => 'Forwarder created',
            ],
            $command->getFormValues(),
        );

        self::assertSame(
            'action=create&domain=test.test&user=source&email=destination1%40test.test%2Cdestination2%40test.test',
            $command->getRequest()->getBody()->getContents(),
        );
    }

    #[Test]
    public function createEmailForwardsFailed(): void
    {
        $errorResponse = [
            'error' => 'Could not execute your request',
            'result' => 'You do not own that domain',
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($errorResponse, JSON_THROW_ON_ERROR)),
        ]));

        $command = new CreateEmailForward();
        $command->setDomain('test.test');
        $command->setUser('source');
        $command->setEmail(['destination1@test.test']);

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
            'Failed [CreateEmailForward]: Could not execute your request - You do not own that domain',
        );

        $api->loginAs('fake-user')->call($command);
    }
}
