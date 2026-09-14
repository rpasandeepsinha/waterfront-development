<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use JsonException;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Events\ReplaceParkingAndUpdateDns;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class HostingDnsUpdatingListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::DNS->value;

    public function __construct(
        private readonly DnsService $dnsService,
    ) {
    }

    public function handle(ReplaceParkingAndUpdateDns $event): void
    {
        try {
            Log::info(
                'Update dns for hosting',
                [
                    LoggingContextKeys::DOMAIN_NAME => $event->getDomain(),
                    LoggingContextKeys::META => [
                        'dnsRecords' => $event->getChanges()->getChangedRows(),
                    ],
                ],
            );
            $this->dnsService->applyDiffReplacingParkingRecords(
                $event->getDomain(),
                $event->getChanges(),
            );
        } catch (DnsZoneNotFoundException|GuzzleException|JsonException|PdnsResponseException $throwable) {
            Log::error(sprintf(
                'Throwable catch: {%s} for domain {%s}',
                $throwable->getMessage(),
                $event->getDomain(),
            ));

            $this->fail($throwable);
        }
    }
}
