<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Listeners;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\DNS\Actions\DisableZonePresigningAction;
use Waterfront\Domain\DNS\Actions\UpdateNameserverAndSoaAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\Environment;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Webmozart\Assert\Assert;

class DnsCreationListener implements ShouldQueue
{
    use InteractsWithQueue;

    private const int MAX_ATTEMPTS = 4;

    public int $tries = self::MAX_ATTEMPTS;

    public string $queue = QueueName::DNS->value;

    public ?CreateDns $event = null;

    public function __construct(
        private readonly DnsService $dnsService,
        private readonly LoggerInterface $logger,
        private readonly UpdateNameserverAndSoaAction $updateNameserverAndSoaAction,
        private readonly DisableZonePresigningAction $disableZonePresigningAction,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly Dispatcher $eventDispatcher,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly Environment $environment,
    ) {
    }

    public function failed(CreateDns $event): void
    {
        $dnsSubscription = Subscription::where('uuid', $event->subscriptionUuid)->firstOrFail();
        $dnsSubscription->technical_status = TechnicalStatus::FAILED->value;
        $dnsSubscription->save();
    }

    /**
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function handle(CreateDns $event): void
    {
        $this->event = $event;

        $domain = $event->domain;
        Assert::stringNotEmpty($domain);

        $dnsSubscription = Subscription::where('uuid', $event->subscriptionUuid)
            ->firstOrFail();

        /** @var Subscription $dnsSubscription */
        $isPremiumDns = $this->dnsProductSpecRepository->isPremiumDns($dnsSubscription->product);

        $this->logger->info(
            'Creating DNS zone: {domain.name} (attempt: {job.attempt})',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
            ]
        );

        $dnsDeployment = $dnsSubscription->dnsDeployment;
        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        $nameservers = $this->dnsDeploymentRepository->getNameservers($dnsDeployment);

        $zoneExists = $this->dnsService->hasDnsZone($domain);

        if ($zoneExists) {
            $this->handleExistingZone($domain, $nameservers);
        }

        try {
            $dnsSubscription->technical_status = TechnicalStatus::PENDING->value;
            $dnsSubscription->save();

            if (! $zoneExists) {
                $this->dnsService->createDnsZone(
                    domain: $domain,
                    nameservers: $nameservers,
                );
            }

            if ($isPremiumDns) {
                $this->dnsService->enablePremiumDns($domain);
                return;
            }

            $dnsSubscription->technical_status = TechnicalStatus::OK->value;
            $dnsSubscription->save();

            /**
             * After we have successfully created the DNS zone, we sleep
             * for a while to make sure the zone is fully propagated.
             *
             * @TODO: This is a temporary fix for SWD-6866 and will be properly fixed in SWD-6877
             */
            if ($this->environment !== Environment::TST && $this->environment !== Environment::DEV) {
                sleep(10);
            }

            $this->eventDispatcher->dispatch(new DnsProvisioned($dnsSubscription->dnsDeployment));
        } catch (Throwable $throwable) { // @phpstan-ignore-line the re-throw of this will be done by the fail() method.
            if ($this->attempts() < self::MAX_ATTEMPTS) {
                //retry with an exponential delay.
                $delay = $this->attempts() ** 2;

                $this->logger->error(
                    'Error while creating DNS zone for {domain.name} (attempts: {job.attempt}/{job.max_attempts}, retrying in: {job.delay} seconds), error: {error.message}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                        LoggingContextKeys::EXCEPTION => $throwable,
                    ]
                );

                $this->release($delay);
            } else {
                $this->logger->error(
                    'Error while creating DNS zone for {domain.name} (max attempts exceeded), error: {error.message}',
                    [
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::EXCEPTION => $throwable,
                    ]
                );

                $this->fail($throwable);
            }
        }
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws DnsZoneNotFoundException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function handleExistingZone(string $domain, array $nameservers): void
    {
        if ($this->dnsService->isSlaveZone($domain)) {
            $this->logger->info(
                'Create dns, zone already exists for domain {domain.name}. Updating kind to master',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ]
            );

            $this->dnsService->changeToMasterAndEmptyMasters($domain);

            $this->logger->info(
                'Create dns, zone already exists for domain {domain.name}. Disabling presigned',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                ]
            );
            $this->disableZonePresigningAction->disable($domain);
        }

        $this->logger->info(
            'Create dns, zone already exists for domain {domain.name}. Trying to update any legacy NS- and SOA-records',
            [
                LoggingContextKeys::DOMAIN_NAME => $domain,
            ]
        );
        $this->updateNameserverAndSoaAction->updateRecords($domain, $nameservers);
    }
}
