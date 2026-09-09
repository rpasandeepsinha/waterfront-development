<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Harbor\Consume;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;

#[CoversClass(Consume::class)]
class HarborConsumeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $channel = self::createMock(AMQPChannel::class);
        $channel->expects(self::once())->method('queue_bind')->willReturn(null);
        $channel->expects(self::once())->method('queue_declare')->willReturn(null);
        $channel->expects(self::once())->method('exchange_declare')->willReturn(null);
        $channel->expects(self::once())->method('basic_consume')->willReturn(null);
        $channel->expects(self::exactly(5))->method('wait')->willReturn(null);
        $channel->expects(self::once())->method('is_open')->willReturn(true);
        $channel->expects(self::once())->method('close')->willReturn(null);

        $connection = self::createMock(AMQPStreamConnection::class);
        $connection->expects(self::once())->method('channel')->willReturn($channel);
        $connection->expects(self::once())->method('isConnected')->willReturn(true);
        $connection->expects(self::once())->method('close')->willReturn(null);

        $harbor = self::createMock(CommunicatesWithHarbor::class);
        $harbor->expects(self::once())->method('amqpConnection')->willReturn($connection);

        $this->app->bind(CommunicatesWithHarbor::class, fn (): CommunicatesWithHarbor => $harbor);
    }

    #[Test]
    public function consume(): void
    {
        Log::shouldReceive('debug')
            ->times(3)
            ->withArgs(fn ($message): bool => in_array(
                $message,
                [
                    'Setting up connection and channel',
                    'Finished setting up connection and channel',
                    'Shutting down Harbor consumer',
                ],
                true,
            ));

        Artisan::call(Consume::class);
    }
}
