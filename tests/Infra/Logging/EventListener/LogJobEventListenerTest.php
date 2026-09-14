<?php

declare(strict_types=1);

namespace Tests\Infra\Logging\EventListener;

use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Email\Jobs\AddDomainToSpamFilter;
use Waterfront\Infra\Logging\EventListener\LogJobEventListener;

#[CoversClass(LogJobEventListener::class)]
class LogJobEventListenerTest extends TestCase
{
    #[Test]
    public function handleJobUsesJobClassName(): void
    {
        // Faking the queue class to make the payload logic publicly accessible
        $queueClass = new class() extends Queue {
            public function createPayload($job, $queue, $data = '', $delay = null)
            {
                return parent::createPayload($job, $queue, $data);
            }
        };

        // Doesn't matter which job we chose here, it just has to be something that's not an anonymous class.
        $realJob = new AddDomainToSpamFilter('bla.nl', null);

        $queue = new $queueClass();
        $paylod = $queue->createPayload($realJob, 'queue');

        $syncJob = new SyncJob(self::createStub(Container::class), $paylod, 'connectionName', 'queue');
        $event = new JobProcessed('connectionName', $syncJob);

        $logger = self::createMock(LoggerInterface::class);

        // Just check if the log message contains the right class
        $logger->expects(self::once())->method('info')->with(self::stringContains(AddDomainToSpamFilter::class));

        $eventListener = new LogJobEventListener($logger);
        $eventListener->handle($event);
    }
}
