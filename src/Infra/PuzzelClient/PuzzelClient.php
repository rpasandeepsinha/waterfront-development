<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient;

use Cache;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\SaloonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Infra\PuzzelClient\Config\ConnectorConfig;
use Waterfront\Infra\PuzzelClient\Connectors\PuzzelConnector;
use Waterfront\Infra\PuzzelClient\DTO\Callback;
use Waterfront\Infra\PuzzelClient\DTO\QueueList;
use Waterfront\Infra\PuzzelClient\DTO\RequestVisualQueueGet;
use Waterfront\Infra\PuzzelClient\DTO\ScheduledCallbackResponse;
use Waterfront\Infra\PuzzelClient\DTO\VisualQueueList;
use Waterfront\Infra\PuzzelClient\Enums\Result;
use Waterfront\Infra\PuzzelClient\Exceptions\PuzzelResponseMissingRedirectException;
use Waterfront\Infra\PuzzelClient\Requests\CreateCallback;
use Waterfront\Infra\PuzzelClient\Requests\RequestQueues;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueue;
use Waterfront\Infra\PuzzelClient\Requests\RequestVisualQueues;
use Waterfront\Infra\PuzzelClient\Serializers\PuzzelSerializerFactory;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class PuzzelClient
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly PuzzelConnector $connector,
        private readonly ConnectorConfig $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->serializer = PuzzelSerializerFactory::get();
    }

    /**
     * @throws SaloonException
     * @throws ExceptionInterface
     */
    public function getQueueItems(): RequestVisualQueueGet
    {
        $cacheKey = sprintf('puzzel_visual_queue_id-%s', $this->config->callbackQueue);
        $visualQueueId = Cache::rememberForever($cacheKey, fn () => $this->getVisualQueueId());
        $request = new RequestVisualQueue($this->config, is_numeric($visualQueueId) ? intval($visualQueueId) : 0);
        $response = $this->connector->send($request);

        return $this->serializer->deserialize($response->body(), RequestVisualQueueGet::class, 'json');
    }

    /**
     * @throws SaloonException
     * @throws ExceptionInterface
     */
    public function getSystemQueues(): QueueList
    {
        $request = new RequestQueues($this->config);
        $response = $this->connector->send($request);

        return $this->serializer->deserialize($response->body(), QueueList::class, 'json');
    }

    public function createCallback(Callback $callback): ScheduledCallbackResponse
    {
        $request = new CreateCallback($this->config, $callback);
        try {
            $response = $this->connector->send($request);
        } catch (SaloonException $exception) {
            $this->logger->error('Error during create callback request.', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'callback_description' => $callback->description,
                    'callback_scheduledTime' => $callback->scheduledDateTime,
                    'callback_phone_number' => $callback->phoneNumber->getRawNumber(),
                ],
            ]);

            return new ScheduledCallbackResponse(
                status: Result::ERROR,
                message: 'Something went wrong during the creation of the callback.',
            );
        }

        $redirectHeaderUrl = $response->header('Location');

        try {
            Assert::stringNotEmpty($redirectHeaderUrl);
        } catch (InvalidArgumentException $exception) {
            throw new PuzzelResponseMissingRedirectException($response, previous: $exception);
        }

        $status = match($redirectHeaderUrl) {
            CreateCallback::REDIRECT_OK => Result::SUCCESS,
            default => Result::ERROR
        };

        $message = match($redirectHeaderUrl) {
            CreateCallback::REDIRECT_OK => 'Callback created successfully',
            CreateCallback::REDIRECT_FULL => 'The queue for callbacks is full.',
            default => $this->getErrorMessageFromRedirectUrl($redirectHeaderUrl)
        };

        return new ScheduledCallbackResponse(
            status: $status,
            message: $message,
        );
    }

    private function getErrorMessageFromRedirectUrl(string $url): string
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        $unParsableUrlMessage = sprintf('An unknown error occurred, original redirect: [%s].', $url);

        if (! is_string($queryString)) {
            return $unParsableUrlMessage;
        }

        parse_str($queryString, $queryParams);

        return array_key_exists('errorMessage', $queryParams) && is_string($queryParams['errorMessage'])
            ? urldecode($queryParams['errorMessage'])
            : $unParsableUrlMessage;
    }

    private function getVisualQueueId(): ?int
    {
        $getQueues = new RequestVisualQueues($this->config);
        $response = $this->connector->send($getQueues);
        $visualQueueList = $this->serializer->deserialize($response->body(), VisualQueueList::class, 'json');

        $visualQueueId = null;

        foreach ($visualQueueList->result as $visualQueue) {
            foreach ($visualQueue->queues as $queueItem) {
                if ($queueItem->key === $this->config->callbackQueue) {
                    $visualQueueId = $visualQueue->id;
                    break 2;
                }
            }
        }

        return $visualQueueId;
    }
}
