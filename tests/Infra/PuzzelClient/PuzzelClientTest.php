<?php

declare(strict_types=1);

namespace Tests\Infra\PuzzelClient;

use Carbon\CarbonImmutable;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Propaganistas\LaravelPhone\PhoneNumber;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\SaloonException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;
use Tests\TestCase;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\Connectors\PuzzelConnector;
use Waterfront\Infra\PuzzelClient\DTO\AccessPoint;
use Waterfront\Infra\PuzzelClient\DTO\Callback;
use Waterfront\Infra\PuzzelClient\Enums\MediaType;
use Waterfront\Infra\PuzzelClient\Enums\RequestStatus;
use Waterfront\Infra\PuzzelClient\Enums\Result;
use Waterfront\Infra\PuzzelClient\Exceptions\PuzzelResponseMissingRedirectException;
use Waterfront\Infra\PuzzelClient\PuzzelClient;
use Waterfront\Infra\PuzzelClient\Requests\CreateCallback;
use Waterfront\Infra\PuzzelClient\Requests\RequestQueues;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueue;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueues;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Infra\SaloonClient\Faking\OAuthMockClient;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(PuzzelClient::class)]
class PuzzelClientTest extends TestCase
{
    private ConnectorConfig $config;

    public function setUp(): void
    {
        parent::setUp();

        $this->config = new ConnectorConfig(
            authUrl: 'https://app.test-puzzel.com',
            apiUrl: 'https://api.test-puzzel.com',
            clientId: '1111111-test-123',
            clientSecret: 'testsecret',
            tenantId: 1111337,
            userId: 1337111111,
            accessPoint: new AccessPoint('0031123456789', 'NO'),
            callbackQueue: 'q_testing_queue',
            retryConfig: new RetryConfig(),
        );
    }

    #[Test]
    public function requestQueueSuccess(): void
    {
        $puzzelConnector = new PuzzelConnector(
            $this->config,
            $this->app->make(LoggerInterface::class),
            $this->app->make(JsonLogMasker::class),
            $this->app->make(Repository::class),
        );

        $mockQueueResponse = (string) file_get_contents(__DIR__ . '/data/queue-with-item.json');
        $mockQueuesResponse = (string) file_get_contents(__DIR__ . '/data/visual-queues.json');
        $mockClient = new OAuthMockClient([
            RequestVisualQueues::class => MockResponse::make($mockQueuesResponse),
            RequestVisualQueue::class => MockResponse::make($mockQueueResponse),
        ]);

        $puzzelConnector->withMockClient($mockClient);

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $this->app->make(LoggerInterface::class),
        );

        $result = $client->getQueueItems();

        $mockClient->assertSent(RequestVisualQueue::class);

        self::assertIsArray($result->result);
        self::assertIsInt($result->code);
        self::assertIsString($result->id);
        self::assertIsString($result->message);

        self::assertCount(2, $result->result);
        $firstItem = $result->result[0];
        $secondItem = $result->result[1];

        self::assertEquals(MediaType::Phone, $firstItem->mediaType);
        self::assertEquals(RequestStatus::InQueue, $firstItem->requestStatus);
        self::assertEquals('004712345678', $firstItem->requestRemoteAddress);
        self::assertNull($firstItem->callbackScheduledTime);

        self::assertEquals(MediaType::Chat, $secondItem->mediaType);
        self::assertEquals(RequestStatus::Allocated, $secondItem->requestStatus);
        self::assertEquals('192.168.1.1', $secondItem->requestRemoteAddress);
        self::assertNotNull($secondItem->callbackScheduledTime);
        self::assertSame('2023-10-27 10:30:00', $secondItem->callbackScheduledTime->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function systemQueuesSuccess(): void
    {
        $expectedKeyInQueues = 'q_testing_queue';
        $expectedQueueId = 38342;

        $puzzelConnector = new PuzzelConnector(
            $this->config,
            $this->app->make(LoggerInterface::class),
            $this->app->make(JsonLogMasker::class),
            $this->app->make(Repository::class),
        );

        $mockQueuesResponse = (string) file_get_contents(__DIR__ . '/data/queues.json');
        $mockClient = new OAuthMockClient([
            RequestQueues::class => MockResponse::make($mockQueuesResponse),
        ]);

        $puzzelConnector->withMockClient($mockClient);

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $this->app->make(LoggerInterface::class),
        );

        $result = $client->getSystemQueues();

        $mockClient->assertSent(RequestQueues::class);

        self::assertIsArray($result->result);
        self::assertIsInt($result->code);
        self::assertIsString($result->id);
        self::assertIsString($result->message);

        self::assertCount(3, $result->result);

        self::assertEquals($expectedKeyInQueues, $result->result[2]->key);
        self::assertEquals($expectedQueueId, $result->result[2]->id);
    }

    #[Test]
    public function createCallbackSuccess(): void
    {
        $testPhoneNumber = '0612345678';
        $expectedSendNumber = '0031612345678';
        $testCallback = new Callback(
            description: 'Call be me back please',
            category: 'test category',
            phoneNumber: new PhoneNumber($testPhoneNumber, 'NL'),
            scheduledDateTime: CarbonImmutable::now()->addDay(),
        );

        $mockResponse = self::mock(Response::class);
        $mockResponse->expects('header')->with('Location')->andReturn(CreateCallback::REDIRECT_OK);

        $puzzelConnector = self::mock(PuzzelConnector::class);
        $puzzelConnector
            ->expects('send')
            ->withArgs(
                fn (CreateCallback $receivedCallback) => (
                    $receivedCallback->body()->get('requestDescription') === $testCallback->description
                    && $receivedCallback->body()->get('requestCategory') === $testCallback->category
                    && $receivedCallback->body()->get('callbackNumber') === $expectedSendNumber
                    && $receivedCallback->body()->get(
                        'scheduledDateTime',
                    ) === $testCallback->scheduledDateTime->format(CreateCallback::PUZZEL_ISO8601_NO_TIMEZONE_FORMAT)
                ),
            )
            ->andReturn($mockResponse);

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $this->app->make(LoggerInterface::class),
        );

        $result = $client->createCallback($testCallback);

        self::assertSame('Callback created successfully', $result->message);
        self::assertSame(Result::SUCCESS, $result->status);
    }

    #[Test]
    public function createCallbackSaloonError(): void
    {
        $mockLogger = self::mock(LoggerInterface::class);

        $testPhoneNumber = '0612345678';
        $testCallback = new Callback(
            description: 'Call be me back please',
            category: 'test category',
            phoneNumber: new PhoneNumber($testPhoneNumber, 'NL'),
            scheduledDateTime: CarbonImmutable::now()->addDay(),
        );

        $puzzelConnector = self::mock(PuzzelConnector::class);
        $exception = new SaloonException('TooManyRequests');
        $puzzelConnector->expects('send')->andThrow($exception);

        $mockLogger->expects('error')->with(
            'Error during create callback request.',
            [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'callback_description' => $testCallback->description,
                    'callback_scheduledTime' => $testCallback->scheduledDateTime,
                    'callback_phone_number' => $testCallback->phoneNumber->getRawNumber(),
                ],
            ],
        );

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $mockLogger,
        );

        $result = $client->createCallback($testCallback);

        self::assertSame('Something went wrong during the creation of the callback.', $result->message);
        self::assertSame(Result::ERROR, $result->status);
    }

    #[Test]
    public function createCallbackErrorResponse(): void
    {
        $testPhoneNumber = '0612345678';
        $expectedSendNumber = '0031612345678';

        $testCallback = new Callback(
            description: 'Call be me back please',
            category: 'test category',
            phoneNumber: new PhoneNumber($testPhoneNumber, 'NL'),
            scheduledDateTime: CarbonImmutable::now()->addDay(),
        );

        $expectedErrorMessage = 'Puzzel API is offline. Please try again later.';
        $errorResponse = sprintf(
            '%s?errorMessage=%s',
            CreateCallback::REDIRECT_ERROR,
            urlencode($expectedErrorMessage),
        );

        $mockResponse = self::mock(Response::class);
        $mockResponse->expects('header')->with('Location')->andReturn($errorResponse);

        $puzzelConnector = self::mock(PuzzelConnector::class);
        $puzzelConnector
            ->expects('send')
            ->withArgs(
                fn (CreateCallback $receivedCallback) => (
                    $receivedCallback->body()->get('requestDescription') === $testCallback->description
                    && $receivedCallback->body()->get('requestCategory') === $testCallback->category
                    && $receivedCallback->body()->get('callbackNumber') === $expectedSendNumber
                    && $receivedCallback->body()->get(
                        'scheduledDateTime',
                    ) === $testCallback->scheduledDateTime->format(CreateCallback::PUZZEL_ISO8601_NO_TIMEZONE_FORMAT)
                ),
            )
            ->andReturn($mockResponse);

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $this->app->make(LoggerInterface::class),
        );

        $result = $client->createCallback($testCallback);
        self::assertSame($expectedErrorMessage, $result->message);
        self::assertSame(Result::ERROR, $result->status);
    }

    #[Test]
    public function createCallbackThrowsExceptionWithoutRedirect(): void
    {
        $testPhoneNumber = '0612345678';
        $expectedSendNumber = '0031612345678';
        $testCallback = new Callback(
            description: 'Call be me back please',
            category: 'test category',
            phoneNumber: new PhoneNumber($testPhoneNumber, 'NL'),
            scheduledDateTime: CarbonImmutable::now()->addDay(),
        );

        $puzzelResponseBody = 'Puzzel response body';
        $mockResponse = self::mock(Response::class);
        $mockResponse->expects('header')->with('Location')->andReturn('');

        $mockResponse->expects('body')->andReturn($puzzelResponseBody);

        $puzzelConnector = self::mock(PuzzelConnector::class);
        $puzzelConnector
            ->expects('send')
            ->withArgs(
                fn (CreateCallback $receivedCallback) => (
                    $receivedCallback->body()->get('requestDescription') === $testCallback->description
                    && $receivedCallback->body()->get('requestCategory') === $testCallback->category
                    && $receivedCallback->body()->get('callbackNumber') === $expectedSendNumber
                    && $receivedCallback->body()->get(
                        'scheduledDateTime',
                    ) === $testCallback->scheduledDateTime->format(CreateCallback::PUZZEL_ISO8601_NO_TIMEZONE_FORMAT)
                ),
            )
            ->andReturn($mockResponse);

        $client = new PuzzelClient(
            connector: $puzzelConnector,
            config: $this->config,
            logger: $this->app->make(LoggerInterface::class),
        );

        self::expectException(PuzzelResponseMissingRedirectException::class);
        self::expectExceptionMessageIs(sprintf(
            'Missing redirect in the response from Puzzel. Response: [%s]',
            $puzzelResponseBody,
        ));

        $client->createCallback($testCallback);
    }
}
