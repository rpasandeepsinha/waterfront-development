<?php

declare(strict_types=1);

namespace Tests\Infra\PaytClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Waterfront\Infra\PaytClient\DTO\PaytCredentials;
use Waterfront\Infra\PaytClient\PaytClient;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;

#[CoversClass(PaytClient::class)]
class PaytClientTest extends TestCase
{
    #[Test]
    public function getDebtorByDebtorNumberReturnsDebtors(): void
    {
        $responseBody = (string) json_encode([
            'data' => [
                [
                    'id' => 'debtor-uuid-1',
                    'debtor_number' => '10001234',
                    'name' => 'Bosch-Brink',
                    'primary_email_address' => 'info@bosch-brink.nl',
                    'invoice_email_address' => 'invoices@bosch-brink.nl',
                    'debtor_identifier' => 'EXT-001',
                    'language_code' => 'nl',
                    'postal_address' => [
                        'city' => 'Amsterdam',
                        'country_code' => 'NL',
                        'postal_code' => '1234AB',
                        'region' => null,
                        'street_1' => 'Teststraat 1',
                        'street_2' => null,
                    ],
                    'administration_id' => 'admin-uuid-1',
                ],
            ],
        ]);

        $container = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $responseBody)]));
        $stack->push(Middleware::history($container));

        $debtors = $this->makeClient($stack)->getDebtorByDebtorNumber('10001234');

        self::assertCount(1, $debtors);
        self::assertSame('debtor-uuid-1', $debtors[0]->id);
        self::assertSame('10001234', $debtors[0]->debtorNumber);
        self::assertSame('Bosch-Brink', $debtors[0]->name);
        self::assertSame('info@bosch-brink.nl', $debtors[0]->primaryEmailAddress);
        self::assertNotNull($debtors[0]->postalAddress);
        self::assertSame('Amsterdam', $debtors[0]->postalAddress->city);
        self::assertSame('NL', $debtors[0]->postalAddress->countryCode);

        assert(is_array($container));
        self::assertArrayHasKey(0, $container);
        $request = $container[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertSame('Bearer test-api-key', $request->getHeaderLine('Authorization'));
        self::assertStringContainsString('administration_id=admin-uuid-1', (string) $request->getUri()->getQuery());
        self::assertStringContainsString('debtor_numbers=10001234', (string) $request->getUri()->getQuery());
    }

    #[Test]
    public function getDebtorByDebtorNumberReturnsEmptyArrayWhenNoData(): void
    {
        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['data' => []])),
        ])));

        self::assertSame([], $client->getDebtorByDebtorNumber('99999'));
    }

    #[Test]
    public function getDebtorByDebtorNumberThrowsOnUnauthorized(): void
    {
        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(401, [], (string) json_encode(['code' => 'unauthorized', 'message' => 'Not authenticated'])),
        ])));

        $this->expectException(GuzzleException::class);

        $client->getDebtorByDebtorNumber('10001234');
    }

    #[Test]
    public function getDebtorByDebtorNumberThrowsOnServerError(): void
    {
        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(
                500,
                [],
                (string) json_encode(['code' => 'server_error', 'message' => 'Internal server error']),
            ),
        ])));

        $this->expectException(GuzzleException::class);

        $client->getDebtorByDebtorNumber('10001234');
    }

    #[Test]
    public function getMessagesByInvoiceIdReturnsMessages(): void
    {
        $responseBody = (string) json_encode([
            'data' => [
                [
                    'id' => '10959712',
                    'sender_type' => 'debtor',
                    'subject' => null,
                    'credit_case_id' => null,
                    'content' => 'Hallo, ik heb een vraag.',
                    'sent_at' => null,
                    'received_at' => '2026-04-04T14:54:09.706377Z',
                ],
                [
                    'id' => '10959713',
                    'sender_type' => 'creditor',
                    'subject' => null,
                    'credit_case_id' => null,
                    'content' => 'Beste klant, bedankt voor uw bericht.',
                    'sent_at' => '2026-04-04T14:54:28.519681Z',
                    'received_at' => null,
                ],
            ],
            'pagination' => ['cursor' => 'abc123'],
        ]);

        $container = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $responseBody)]));
        $stack->push(Middleware::history($container));

        $messages = $this->makeClient($stack)->getMessagesByInvoiceId('241312259');

        self::assertCount(2, $messages);
        self::assertSame('10959712', $messages[0]->id);
        self::assertSame('debtor', $messages[0]->senderType);
        self::assertSame('Hallo, ik heb een vraag.', $messages[0]->content);
        self::assertNull($messages[0]->sentAt);
        self::assertSame('2026-04-04T14:54:09.706377Z', $messages[0]->receivedAt);
        self::assertNull($messages[0]->subject);
        self::assertSame('10959713', $messages[1]->id);
        self::assertSame('creditor', $messages[1]->senderType);
        self::assertSame('2026-04-04T14:54:28.519681Z', $messages[1]->sentAt);
        self::assertNull($messages[1]->receivedAt);

        assert(is_array($container));
        self::assertArrayHasKey(0, $container);
        $request = $container[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertStringContainsString('invoice_ids=241312259', (string) $request->getUri()->getQuery());
        self::assertStringContainsString('administration_id=admin-uuid-1', (string) $request->getUri()->getQuery());
    }

    #[Test]
    public function getMessagesByDebtorIdReturnsMessages(): void
    {
        $responseBody = (string) json_encode([
            'data' => [
                [
                    'id' => '10959712',
                    'sender_type' => 'debtor',
                    'subject' => null,
                    'credit_case_id' => null,
                    'content' => 'Hallo, ik heb een vraag.',
                    'sent_at' => '2026-04-04T14:54:09.706377Z',
                ],
                [
                    'id' => '10959713',
                    'sender_type' => 'creditor',
                    'subject' => null,
                    'credit_case_id' => null,
                    'content' => 'Beste klant, bedankt voor uw bericht.',
                    'sent_at' => '2026-04-04T14:54:28.519681Z',
                ],
            ],
            'pagination' => ['cursor' => 'abc123'],
        ]);

        $container = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $responseBody)]));
        $stack->push(Middleware::history($container));

        $messages = $this->makeClient($stack)->getMessagesByDebtorId('241312259');

        self::assertCount(2, $messages);
        self::assertSame('10959712', $messages[0]->id);
        self::assertSame('10959713', $messages[1]->id);

        assert(is_array($container));
        self::assertArrayHasKey(0, $container);
        $request = $container[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertStringContainsString('debtor_ids=241312259', (string) $request->getUri()->getQuery());
        self::assertStringContainsString('administration_id=admin-uuid-1', (string) $request->getUri()->getQuery());
    }

    #[Test]
    public function getMessagesByCreditCaseIdReturnsMessages(): void
    {
        $responseBody = (string) json_encode([
            'data' => [
                [
                    'id' => '10959712',
                    'sender_type' => 'debtor',
                    'subject' => null,
                    'credit_case_id' => '1219278',
                    'content' => 'Hallo, ik heb een vraag.',
                    'sent_at' => '2026-04-04T14:54:09.706377Z',
                ],
                [
                    'id' => '10959713',
                    'sender_type' => 'creditor',
                    'subject' => null,
                    'credit_case_id' => '1219278',
                    'content' => 'Beste klant, bedankt voor uw bericht.',
                    'sent_at' => '2026-04-04T14:54:28.519681Z',
                ],
            ],
            'pagination' => ['cursor' => 'abc123'],
        ]);

        $container = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], $responseBody)]));
        $stack->push(Middleware::history($container));

        $messages = $this->makeClient($stack)->getMessagesByCreditCaseId('1219278');

        self::assertCount(2, $messages);
        self::assertSame('1219278', $messages[0]->creditCaseId);
        self::assertSame('1219278', $messages[1]->creditCaseId);

        assert(is_array($container));
        self::assertArrayHasKey(0, $container);
        $request = $container[0]['request'];
        self::assertSame('GET', $request->getMethod());
        self::assertStringContainsString('credit_case_ids=1219278', (string) $request->getUri()->getQuery());
    }

    #[Test]
    public function getMessagesByInvoiceIdReturnsEmptyArrayWhenNoData(): void
    {
        $client = $this->makeClient(HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode(['data' => [], 'pagination' => ['cursor' => null]])),
        ])));

        self::assertSame([], $client->getMessagesByInvoiceId('99999'));
    }

    private function makeClient(HandlerStack $stack): PaytClient
    {
        return new PaytClient(
            httpClient: new GuzzleClient(['handler' => $stack]),
            logger: new NullLogger(),
            serializer: PaytSerializerFactory::getSerializer(),
            credentials: new PaytCredentials(apiKey: 'test-api-key', administrationId: 'admin-uuid-1'),
        );
    }
}
