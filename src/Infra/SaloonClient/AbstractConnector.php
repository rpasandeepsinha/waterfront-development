<?php

declare(strict_types=1);

namespace Waterfront\Infra\SaloonClient;

use Exception;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Saloon\Contracts\Body\BodyRepository;
use Saloon\Enums\PipeOrder;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Connector;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;
use Sentry\Tracing\GuzzleTracingMiddleware;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskerInterface;
use Waterfront\Infra\Logging\Masker\Interfaces\MaskKeysInterface;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

abstract class AbstractConnector extends Connector
{
    private const array RETRYABLE_STATUS_CODES = [
        HttpResponse::HTTP_REQUEST_TIMEOUT,
        HttpResponse::HTTP_TOO_MANY_REQUESTS,
        HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
        HttpResponse::HTTP_BAD_GATEWAY,
        HttpResponse::HTTP_SERVICE_UNAVAILABLE,
        HttpResponse::HTTP_GATEWAY_TIMEOUT,
    ];

    public ?int $tries;

    public ?int $retryInterval;

    public ?bool $useExponentialBackoff;

    public function __construct(
        protected LoggerInterface $logger,
        protected MaskerInterface $logMasker,
        RetryConfig $retryConfig = new RetryConfig(),
    ) {
        $this->configureRetries($retryConfig);
        $this->registerSentryMiddleware();
        $this->registerResponseLogger();
    }

    public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
    {
        if (! $this->shouldRetry($exception)) {
            return false;
        }

        $context = [
            LoggingContextKeys::REQUEST_METHOD => $request->getMethod(),
            LoggingContextKeys::REQUEST_URI => $request->resolveEndpoint(),
            LoggingContextKeys::EXCEPTION => $exception,
        ];

        if ($exception instanceof FatalRequestException) {
            $this->logger->warning(
                sprintf('[%s] Retrying request after connection failure.', $this->getBaseClassName()),
                $context,
            );

            return true;
        }

        $this->logger->warning(
            sprintf('[%s] Retrying request after transient HTTP failure.', $this->getBaseClassName()),
            $context + [
                LoggingContextKeys::RESPONSE_CODE => $exception->getResponse()->status(),
            ],
        );

        return true;
    }

    protected function configureRetries(RetryConfig $retryConfig): void
    {
        $this->tries = $retryConfig->tries;
        $this->retryInterval = $retryConfig->intervalMs;
        $this->useExponentialBackoff = $retryConfig->useExponentialBackoff;
    }

    protected function shouldRetry(FatalRequestException|RequestException $exception): bool
    {
        /*
          Saloon uses FatalRequestException for connection level failures.
          We should treat those as transient and always retry them
        */
        if ($exception instanceof FatalRequestException) {
            return true;
        }

        return in_array(
            $exception->getResponse()->status(),
            self::RETRYABLE_STATUS_CODES,
            true,
        );
    }

    protected function registerSentryMiddleware(): void
    {
        try {
            $sender = $this->sender();

            if (! $sender instanceof GuzzleSender) {
                $this->logger->warning(sprintf(
                    '[%s] could not add Sentry middleware to Guzzle since GuzzleSender is no longer the default sender.',
                    $this->getBaseClassName()
                ));
                return;
            }

            $sender->addMiddleware(fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler) {
                $stack = HandlerStack::create($handler);
                $stack->push(GuzzleTracingMiddleware::trace());

                return $stack($request, $options);
            });
        } catch (Exception $exception) {
            $this->logger->error(sprintf(
                '[%s] Error registering sentry middleware on client.',
                $this->getBaseClassName()
            ), [LoggingContextKeys::EXCEPTION => $exception]);
        }
    }

    protected function registerResponseLogger(): void
    {
        $this->middleware()->onResponse(
            function (Response $response) {
                $request = $response->getPsrRequest();

                $pendingRequest = $response->getPendingRequest();
                $body = $pendingRequest->body();
                $requestData = $this->getRequestBodyAsString($body);
                $responseBody = $response->body();
                $saloonRequest = $response->getRequest();

                if ($saloonRequest instanceof MaskKeysInterface) {
                    $requestData = $this->logMasker->mask($requestData, $saloonRequest);
                    $responseBody = $this->logMasker->mask($responseBody, $saloonRequest);
                }

                $message = sprintf(
                    '[%s] "{request.method} {request.uri}" {response.code}',
                    $this->getBaseClassName()
                );
                $this->logger->info(
                    $message,
                    [
                        LoggingContextKeys::REQUEST_DATA => $requestData,
                        LoggingContextKeys::REQUEST_URI => (string) $request->getUri(),
                        LoggingContextKeys::REQUEST_METHOD => $request->getMethod(),
                        LoggingContextKeys::RESPONSE_CODE => $response->status(),
                        LoggingContextKeys::RESPONSE_DATA => $responseBody,
                    ]
                );
            },
            name: 'loggingRequestResponse',
            order: PipeOrder::FIRST
        );
    }

    private function getRequestBodyAsString(?BodyRepository $body): string
    {
        if ($body === null || $body->isEmpty()) {
            return '';
        }

        $data = $body->all();

        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            return json_encode($data, JSON_THROW_ON_ERROR);
        }

        return '';
    }

    /**
     * Simple method to get the class name as a clean string such
     * as 'GandiClient' or 'DirectAdminJsonClient'. It avoids
     * using a ReflectionClass for simplicity.
     *
     * @see https://stackoverflow.com/questions/19901850/how-do-i-get-an-objects-unqualified-short-class-name#comment41007076_25472778
     */
    private function getBaseClassName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }
}
