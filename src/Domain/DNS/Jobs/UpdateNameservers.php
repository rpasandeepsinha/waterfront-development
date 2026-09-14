<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Jobs;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use JsonException;
use Psr\Log\LoggerInterface;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Throwable;
use Waterfront\Domain\DNS\Actions\UpdateNameserverAndSoaAction;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\DTO\Nameserver;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Provision\DNS\Events\DnsProvisioned;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;
use Webmozart\Assert\Assert;

/**
 * Job for updating nameservers to or from Gandi nameservers.
 */
class UpdateNameservers extends AbstractQueueableJob
{
    public int $tries = 7;

    public function __construct(
        private readonly string $domain,
        private readonly bool $useVanityNs,
    ) {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [5, 30, 60, 5 * 60, 15 * 60, 60 * 60];
    }

    public function failed(?Throwable $throwable): void
    {
        $container = Container::getInstance();
        $logger = $container->make(LoggerInterface::class);
        $logger->error(
            'Error UpdateNameServers for domain {domain.name} job definitely failed after {job.attempt} attempts',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::EXCEPTION => $throwable,
            ],
        );

        /** @var SubscriptionRepository $subscriptionRepository */
        $subscriptionRepository = $container->make(SubscriptionRepository::class);
        $dnsSubscription = $subscriptionRepository->getNotAdministrativelyEndedOrSuspendedDnsSubscription($this->domain);

        $dnsSubscription->update([
            'technical_status' => TechnicalStatus::FAILED->value,
        ]);
    }

    /**
     * @throws DomainModificationFailedException
     * @throws FatalRequestException
     * @throws ForbiddenException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws RequestException
     * @throws UnauthorizedException
     */
    public function handle(
        GandiClient $gandiClient,
        UpdateNameserverAndSoaAction $updateNameserverAndSoaAction,
        LoggerInterface $logger,
        SubscriptionRepository $subscriptionRepository,
        DomainDeploymentRepository $domainDeploymentRepository,
        DnsDeploymentRepository $dnsDeploymentRepository,
        DnsService $dnsService,
        Dispatcher $eventDispatcher,
    ): void {
        $logger->debug(
            'Starting UpdateNameServers Job for domain [{domain.name}]. attempt {job.attempt}/{job.max_attempts}',
            [
                LoggingContextKeys::DOMAIN_NAME => $this->domain,
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::META => [
                    'useVanityNs' => $this->useVanityNs,
                ],
            ],
        );

        $domainDeployment = $domainDeploymentRepository->getActiveDeploymentByDomain($this->domain);
        Assert::notNull($domainDeployment, 'Domain deployment not found for the given domain.');

        $dnsSubscription = $subscriptionRepository->getNotAdministrativelyEndedOrSuspendedDnsSubscription($this->domain);

        $dnsDeployment = $dnsSubscription->dnsDeployment;

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($this->domain);
        }

        if ($this->useVanityNs) {
            try {
                $response = $gandiClient->getDnsRecords($this->domain);
            } catch (NotFoundException) {
                $this->release($this->getBackoffDelay());

                return;
            } catch (ClientException $exception) {
                $dnsDeploymentRepository->saveLastPremiumProviderResponse(
                    $dnsDeployment,
                    $exception->getResponse()->body(),
                );
                throw $exception;
            } catch (Exception $exception) {
                $dnsDeploymentRepository->saveLastPremiumProviderResponse($dnsDeployment, $exception->getMessage());
                throw $exception;
            }

            $dnsDeploymentRepository->saveLastPremiumProviderResponse(
                $dnsDeployment,
                json_encode($response, JSON_THROW_ON_ERROR),
            );
        }

        $nameserverCollection = $dnsDeploymentRepository->getNameservers($dnsDeployment);

        $logger->debug('Successful deployment for [{domain.name}] at Gandi', [
            LoggingContextKeys::DOMAIN_NAME => $this->domain,
            LoggingContextKeys::META => [
                'nameservers' => $nameserverCollection,
            ],
        ]);

        $this->updateNameserverForDns($nameserverCollection, $updateNameserverAndSoaAction, $dnsService);

        $dnsSubscription->update([
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $eventDispatcher->dispatch(new DnsProvisioned($dnsDeployment));
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::DNS;
    }

    /**
     * @param Nameserver[] $nameservers
     *
     * @throws JsonException
     * @throws GuzzleException
     * @throws PdnsResponseException
     */
    private function updateNameserverForDns(
        array $nameservers,
        UpdateNameserverAndSoaAction $updateNameserverAndSoaAction,
        DnsService $dnsService,
    ): void {
        // Update nameservers in PowerDNS, enforce the update to bypass legacy NS check.
        $updateNameserverAndSoaAction->updateRecords($this->domain, $nameservers, true);

        if ($this->useVanityNs) {
            $dnsService->sendNotify($this->domain);
        }
    }
}
