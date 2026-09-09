<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Harbor;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[AsCommand(name: 'harbor:consume')]
#[Description('Consume and handle messages sent from Harbor')]
class Consume extends Command
{
    public function handle(CommunicatesWithHarbor $harbor, ConfigurationInterface $configuration, LoggerInterface $logger): void
    {
        $logMessage = 'Setting up connection and channel';
        $this->info($logMessage);
        $logger->debug($logMessage);

        $connection = $harbor->amqpConnection();
        $channel = $connection->channel();

        $this->setupChannel($channel, $configuration, $harbor, $logger);

        $logMessage = 'Finished setting up connection and channel';
        $this->info($logMessage);
        $logger->debug($logMessage);

        $maxMessagesPerRun = $configuration->getAsInteger('harbor.consume_max_per_run');

        try {
            for ($i = 0; $i < $maxMessagesPerRun; $i++) {
                $channel->wait(null, true);
            }
        } finally {
            $this->shutdown($connection, $channel, $logger);
        }
    }

    private function shutdown(AbstractConnection $connection, AMQPChannel $channel, LoggerInterface $logger): void
    {
        $logMessage = 'Shutting down Harbor consumer';
        $this->info($logMessage);
        $logger->debug($logMessage);

        if ($channel->is_open()) {
            $channel->close();
        }

        if ($connection->isConnected()) {
            $connection->close();
        }
    }

    private function setupChannel(AMQPChannel $channel, ConfigurationInterface $configuration, CommunicatesWithHarbor $harbor, LoggerInterface $logger): void
    {
        $channel->queue_declare(
            queue: $configuration->getAsString('harbor.queue_outgoing'),
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false
        );

        $channel->exchange_declare(
            exchange: $configuration->getAsString('harbor.exchange'),
            type: AMQPExchangeType::DIRECT,
            passive: false,
            durable: true,
            auto_delete: false
        );

        $channel->queue_bind(
            queue: $configuration->getAsString('harbor.queue_outgoing'),
            exchange: $configuration->getAsString('harbor.exchange')
        );

        $channel->basic_consume(
            queue: $configuration->getAsString('harbor.queue_outgoing'),
            consumer_tag: $configuration->getAsString('harbor.outgoing_consumer_tag'),
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($harbor, $logger): void {
                $logMessage = sprintf(
                    'Received Harbor message: %s',
                    substr($message->getBody(), 0, 400)
                );
                $this->info($logMessage);
                $logger->info($logMessage);

                $harbor->receiveMessage($message);
            }
        );
    }
}
