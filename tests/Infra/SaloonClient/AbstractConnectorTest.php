<?php

declare(strict_types=1);

namespace Tests\Infra\SaloonClient;

use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;
use Tests\TestCase;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\SaloonClient\AbstractConnector;

#[CoversClass(AbstractConnector::class)]
class AbstractConnectorTest extends TestCase
{
    #[Test]
    public function requestsAreLogged(): void
    {
        $mockLogger = self::mock(LoggerInterface::class);
        $maskStub = self::createStub(MaskerInterface::class);
        $username = 'test.kees';
        $postData = [
            'a' => 'b',
            'c' => 'd',
        ];

        $client = new class(logger: $mockLogger, logMasker: $maskStub) extends AbstractConnector {
            public function resolveBaseUrl(): string
            {
                return 'https://test.com/';
            }
        };

        $clientBasename = basename(str_replace('\\', '/', $client::class));

        $getRequest = new class($username) extends Request {
            protected Method $method = Method::GET;

            public function __construct(
                private readonly string $username,
            ) {
            }

            public function resolveEndpoint(): string
            {
                return sprintf('/users/%s', $this->username);
            }
        };

        $postRequest = new class($postData) extends Request implements HasBody {
            use HasJsonBody;

            protected Method $method = Method::POST;

            /**
             * @param array<string, string> $postData
             */
            public function __construct(
                private readonly array $postData,
            ) {
            }

            public function resolveEndpoint(): string
            {
                return '/users/update';
            }

            /**
             * @return array<string, string>
             */
            public function defaultBody(): array
            {
                return $this->postData;
            }
        };

        $mockClient = new MockClient([
            $getRequest::class => MockResponse::make(body: 'this-is-response-data'),
            $postRequest::class => MockResponse::make(body: '', status: 201),
        ]);

        $client->withMockClient($mockClient);

        $mockLogger
            ->expects('info')
            ->once()
            ->with(
                sprintf('[%s] "{request.method} {request.uri}" {response.code}', $clientBasename),
                [
                    'request.data' => '',
                    'request.uri' => sprintf('https://test.com/users/%s', $username),
                    'request.method' => 'GET',
                    'response.code' => 200,
                    'response.data' => 'this-is-response-data',
                ],
            );

        $client->send($getRequest);

        $mockLogger
            ->expects('info')
            ->once()
            ->with(
                sprintf('[%s] "{request.method} {request.uri}" {response.code}', $clientBasename),
                [
                    'request.data' => json_encode($postData),
                    'request.uri' => 'https://test.com/users/update',
                    'request.method' => 'POST',
                    'response.code' => 201,
                    'response.data' => '',
                ],
            );

        $client->send($postRequest);
    }

    #[Test]
    public function requestBodyIsLoggedWhenPsrStreamIsConsumed(): void
    {
        $mockLogger = self::mock(LoggerInterface::class);
        $maskStub = self::createStub(MaskerInterface::class);
        $postData = [
            'username' => 'john.doe',
            'email' => 'john@example.com',
        ];

        $client = new class(logger: $mockLogger, logMasker: $maskStub) extends AbstractConnector {
            public function resolveBaseUrl(): string
            {
                return 'https://test.com/';
            }

            /**
             * Simulate that the PSR-7 body stream has been consumed by the HTTP client.
             * We read the stream contents, moving the pointer to the end, and return
             * a new request with an empty body just like a real consumer would do.
             */
            public function handlePsrRequest(
                RequestInterface $request,
                PendingRequest $pendingRequest,
            ): RequestInterface {
                return $request->withBody(Utils::streamFor(''));
            }
        };

        $clientBasename = basename(str_replace('\\', '/', $client::class));

        $postRequest = new class($postData) extends Request implements HasBody {
            use HasJsonBody;

            protected Method $method = Method::PUT;

            /**
             * @param array<string, string> $postData
             */
            public function __construct(
                private readonly array $postData,
            ) {
            }

            public function resolveEndpoint(): string
            {
                return '/api/users/update';
            }

            /**
             * @return array<string, string>
             */
            public function defaultBody(): array
            {
                return $this->postData;
            }
        };

        $mockClient = new MockClient([
            $postRequest::class => MockResponse::make(body: '{"status":"ok"}'),
        ]);

        $client->withMockClient($mockClient);

        $mockLogger
            ->expects('info')
            ->once()
            ->with(
                sprintf('[%s] "{request.method} {request.uri}" {response.code}', $clientBasename),
                [
                    'request.data' => json_encode($postData),
                    'request.uri' => 'https://test.com/api/users/update',
                    'request.method' => 'PUT',
                    'response.code' => 200,
                    'response.data' => '{"status":"ok"}',
                ],
            );

        $client->send($postRequest);
    }
}
