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
use Waterfront\Infra\DirectAdminClient\Commands\EmailForwards\ShowEmailForwards;
use Waterfront\Infra\DirectAdminClient\DirectAdminApi;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminEmailForward;
use Waterfront\Infra\DirectAdminClient\Exceptions\DirectAdminCommandException;

#[CoversClass(ShowEmailForwards::class)]
class ShowEmailForwardsTest extends DirectAdminTestCase
{
    #[Test]
    public function getEmailForwards(): void
    {
        $responseBody = [
            'exampleSource' => [
                'destination@local.test',
                'another@local.test',
            ],
            'anotherExampleSource' => [
                'wow@remote.test',
            ],
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(200, $this->getDefaultResponseHeaders(), json_encode($responseBody, JSON_THROW_ON_ERROR)),
        ]));

        $command = new ShowEmailForwards();
        $command->setDomain('test.nl');

        $api = new DirectAdminApi(
            $this->getTestServer(),
            new Client(['handler' => $handlerStack])
        );

        $command = $api->loginAs('fake-user')->call($command);
        $fetchedForwards = $command->getForwards();

        self::assertCount(2, $fetchedForwards);

        $selectedForward = $fetchedForwards[0];
        self::assertInstanceOf(DirectAdminEmailForward::class, $selectedForward);

        self::assertSame('exampleSource', $selectedForward->source);
        self::assertSame(
            [
                'destination@local.test',
                'another@local.test',
            ],
            $selectedForward->destinations
        );

        self::assertSame(
            [
                'exampleSource' => [
                    'destination@local.test',
                    'another@local.test',
                ],
                'anotherExampleSource' => [
                    'wow@remote.test',
                ],
            ],
            $command->getFormValues()
        );
    }

    #[Test]
    public function getEmailForwardsFailed(): void
    {
        $errorResponse = [
            'error' => 'Could not execute your request',
            'result' => 'You do not own that domain',
        ];

        $handlerStack = HandlerStack::create(new MockHandler([
            new Response(500, $this->getDefaultResponseHeaders(), json_encode($errorResponse, JSON_THROW_ON_ERROR)),
        ]));

        $command = new ShowEmailForwards();
        $command->setDomain('test.nl');

        $api = new DirectAdminApi(
            $this->getTestServer(),
            new Client([
                'handler' => $handlerStack,
                // Since we are injecting a custom client we need to ensure the same error handling
                // as the client set through the normal flow.
                'http_errors' => false,
            ])
        );

        $this->expectException(DirectAdminCommandException::class);
        $this->expectExceptionMessageIs('Failed [ShowEmailForwards]: Could not execute your request - You do not own that domain');

        $api->loginAs('fake-user')->call($command);
    }
}
