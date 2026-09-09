<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Events\TerminateDnsZoneEvent;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;

class DnsTerminationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = QueueName::DNS->value;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DnsService $dnsService,
        private readonly ProductRepository $productRepository,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly GandiClient $gandiClient,
    ) {
    }

    public function handle(TerminateDnsZoneEvent $event): void
    {
        try {
            $this->logger->info(
                'Terminating dns zone for subscription {subscription.uuid} domain: {domain.name}',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                    LoggingContextKeys::DOMAIN_NAME => $event->domain,
            ]
            );

            if (! $this->dnsService->isSlaveZone($event->domain)) {
                $product = $this->productRepository->findProductByUuid($event->dnsProductUuid);

                if ($this->dnsProductSpecRepository->isPremiumDns($product)) {
                    $this->dnsService->disablePremiumDns(domain: $event->domain, shouldUpdateNameservers: false);
                    $this->gandiClient->deleteDomain($event->domain);
                }

                $this->dnsService->deleteZone($event->domain);
            } else {
                $this->logger->notice(
                    'slave zone is trying to be deleted for domain {domain.name} , subscription uuid: {subscription.uuid}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $event->domain,
                        LoggingContextKeys::SUBSCRIPTION_UUID => $event->subscriptionUuid,
                ]
                );
            }
        } catch (PdnsResponseException|GuzzleException|ModelNotFoundException $exception) {
            $this->logger->error(
                'Throwable catch: {exception} for domain {domain.name}',
                [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::DOMAIN_NAME => $event->domain,
            ]
            );

            $this->fail($exception);
        }
    }
}
