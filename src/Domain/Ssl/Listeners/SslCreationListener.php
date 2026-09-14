<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Ssl\Events\CreateSsl;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class SslCreationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::PARTNER_SSL->value;

    public int $tries = 7;

    /** @var array<int> */
    public array $backoff = [60, 5 * 60, 30 * 60, 60 * 60, 6 * 60 * 60, 12 * 60 * 60, 24 * 60 * 60];

    public function __construct(
        private readonly CustomerSharedSslService $sslService,
        private readonly DnsService $dnsService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(CreateSsl $event): void
    {
        $this->logger->info(
            sprintf('Create ssl for domain %s', $event->domain),
            [
                LoggingContextKeys::PROVISIONING_ID => $event->sslDeployment->id,
                LoggingContextKeys::PROVISIONING_PROVIDER => $event->sslDeployment->provider->slug,
                LoggingContextKeys::DOMAIN_NAME => $event->domain,
                LoggingContextKeys::META => [
                    'period' => $event->period,
                    'csr' => $event->csr,
                ],
            ],
        );

        if (! $this->dnsService->hasDnsZone($event->domain)) {
            $this->logger->info(
                sprintf(
                    'No dns zone found for %s during ssl creation after %d attempts',
                    $event->domain,
                    $this->attempts(),
                ),
            );
            $this->release($this->backoff[$this->attempts() - 1]);

            return;
        }

        $result = $this->sslService->create($event->period, $event->sslDeployment, $event->csr);

        $this->logger->info(
            sprintf('Create ssl result for domain %s', $event->domain),
            [
                LoggingContextKeys::META => [
                    'result' => json_encode($result->toArray()),
                ],
            ],
        );
    }
}
