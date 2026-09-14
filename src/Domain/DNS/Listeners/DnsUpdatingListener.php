<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class DnsUpdatingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::DNS->value;

    public function __construct(
        private readonly DnsService $dnsService,
    ) {
    }

    public function handle(UpdateDns $event): void
    {
        try {
            Log::info(
                'Update dns',
                [
                    LoggingContextKeys::DOMAIN_NAME => $event->getDomain(),
                    LoggingContextKeys::META => [
                        'dnsRecords' => $event->getChanges()->getChangedRows(),
                    ],
                ],
            );
            $this->dnsService->applyDiff(
                $event->getDomain(),
                $event->getChanges(),
            );
        } catch (Throwable $throwable) {
            Log::error(sprintf(
                'Throwable catch: {%s} for domain {%s}',
                $throwable->getMessage(),
                $event->getDomain(),
            ));

            $this->fail($throwable);
        }
    }
}
