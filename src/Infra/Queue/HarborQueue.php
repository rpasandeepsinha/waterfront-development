<?php

declare(strict_types=1);

namespace Waterfront\Infra\Queue;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use SandwaveIo\HarborMessages\Message\Serializer\JsonSerializer;
use SandwaveIo\HarborMessages\SerializableDataInterface;
use Throwable;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Queue\Exceptions\HarborClientException;
use Webmozart\Assert\Assert;

/**
 * Provides a stable connection with the queue dedicated to Harbor.
 */
class HarborQueue
{
    private ?AMQPStreamConnection $streamConnection = null;

    private ?AMQPChannel $channel = null;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly JsonSerializer $serializer,
    ) {
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param SerializableDataInterface<TKey, TValue> $message
     *
     * @throws HarborClientException
     */
    public function publish(SerializableDataInterface $message): void
    {
        if (! $this->configuration->getAsBoolean('harbor.enabled')) {
            return;
        }

        try {
            $this->getChannel()->basic_publish(
                msg: new AMQPMessage(
                    body: $this->serializer->encode($message),
                    properties: ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
                ),
                exchange: 'messages',
            );
        } catch (Throwable $exception) {
            throw new HarborClientException(
                $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }

    private function getChannel(): AMQPChannel
    {
        if ($this->channel === null) {
            $this->setupStreamConnection();
            $this->setupChannel();

            Assert::notNull($this->channel);
        }

        return $this->channel;
    }

    /**
     * @throws Exception
     */
    private function setupStreamConnection(): void
    {
        $this->streamConnection = new AMQPStreamConnection(
            $this->configuration->getAsString('harbor.host'),
            $this->configuration->getAsInteger('harbor.port'),
            $this->configuration->getAsString('harbor.user'),
            $this->configuration->getAsString('harbor.password'),
            $this->configuration->getAsString('harbor.vhost'),
        );
    }

    private function setupChannel(): void
    {
        Assert::notNull($this->streamConnection);

        $this->channel = $this->streamConnection->channel();
        $this->channel->queue_bind(
            queue: $this->configuration->getAsString('harbor.queue_incoming'),
            exchange: $this->configuration->getAsString('harbor.exchange'),
        );
    }
}
