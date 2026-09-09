<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Services;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\Domain\ProcessCollection;
use Throwable;
use Waterfront\Apps\OneOffScripts\Domain\DTO\FailedDomainSubscriptionRepair;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

class FailedDomainSubscriptionRepairService
{
    public function __construct(
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly NameserverAssignerFactory $nameserverAssignerFactory,
        private readonly LoggerInterface $logger,
        private readonly RtrService $rtrService,
    ) {
    }

    public function determineRepair(Subscription $subscription, string $source): FailedDomainSubscriptionRepair
    {
        $domainDeployment = $subscription->domainDeployment;
        $logContext = $this->buildLogContext($subscription, $source, $domainDeployment);

        $domain = $subscription->domain;
        if ($domain === null) {
            $this->logger->warning(
                'Skipping failed domain subscription because no domain name was found on the subscription.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_name',
                    ],
                ],
            );

            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SKIP,
                reason: 'missing_domain_name',
            );
        }

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->logger->warning(
                'Skipping failed domain subscription because no domain deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_deployment',
                    ],
                ],
            );

            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SKIP,
                reason: 'missing_domain_deployment',
            );
        }

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);
        if (! $dnsDeployment instanceof DnsDeployment) {
            $this->logger->info(
                'Skipping failed domain subscription because no DNS deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_dns_deployment',
                    ],
                ],
            );

            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SKIP,
                reason: 'missing_dns_deployment',
            );
        }

        try {
            $remoteDomain = $this->rtrService->fetchDomain($domain);
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            if ($exception instanceof DomainDoesNotExistException || $exception->getMessage() === 'Not found.') {
                try {
                    $processes = $this->rtrService->listProcessesForDomain($domain);
                } catch (Throwable) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
                    $this->logger->warning(
                        'Skipping failed domain subscription because RTR domain lookup failed.',
                        $logContext + [
                            LoggingContextKeys::EXCEPTION => $exception,
                            LoggingContextKeys::META => [
                                'reason' => 'rtr_domain_lookup_failed',
                            ],
                        ],
                    );

                    return new FailedDomainSubscriptionRepair(
                        path: FailedDomainSubscriptionRepairPath::SKIP,
                        reason: 'rtr_domain_lookup_failed',
                    );
                }

                /* If we don't have a domain, but we did find a process at
                 * RTR we try to restore the current domain subscription
                 * from that. Else retry the entire RTR registration
                 */
                return $this->findLatestOpenDomainProcess($processes) !== null
                    ? new FailedDomainSubscriptionRepair(
                        path: FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS,
                        processCollection: $processes,
                        reason: 'remote_processes_found_no_domain',
                    )
                    : new FailedDomainSubscriptionRepair(
                        path: FailedDomainSubscriptionRepairPath::RETRY_PROVISIONING,
                        reason: 'remote_domain_missing',
                    );
            }

            $this->logger->warning(
                'Skipping failed domain subscription because RTR domain lookup failed.',
                $logContext + [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'reason' => 'rtr_domain_lookup_failed',
                    ],
                ],
            );

            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SKIP,
                reason: 'rtr_domain_lookup_failed',
            );
        }

        $this->logger->info(
            'Fetched remote domain for failed subscription.',
            $logContext + [
                LoggingContextKeys::META => [
                    'remote_statuses' => $remoteDomain->status,
                    'remote_nameservers' => $remoteDomain->ns,
                ],
            ],
        );

        /* If the domain was found, it might still be possible that it has
         * open processes such as pending validation that would allow us
         *  to restore the subscription to a pending state correctly.
         */
        try {
            $processes = $this->rtrService->listProcessesForDomain($domain);
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Skipping failed domain subscription because RTR process lookup failed (domain is present).',
                $logContext + [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'reason' => 'rtr_domain_lookup_failed',
                    ],
                ],
            );

            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SKIP,
                reason: 'rtr_domain_lookup_failed',
            );
        }

        if ($this->findLatestOpenDomainProcess($processes) !== null) {
            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS,
                processCollection: $processes,
                reason: 'remote_processes_found_no_domain',
            );
        }

        if (in_array(RtrDomainStatus::OK->value, $remoteDomain->status, true)) {
            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY,
                reason: 'remote_status_is_ok',
            );
        }

        if (in_array(RtrDomainStatus::PENDING_VALIDATION->value, $remoteDomain->status, true)) {
            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY,
                reason: 'remote_status_pending'
            );
        }

        $storedNameservers = $this->dnsDeploymentRepository->getNameserverHostnames($dnsDeployment);

        // check if both RTR and our DnsDeployment contain the same number of nameservers with the same content
        if (
            count($storedNameservers) === 0
            || count($remoteDomain->ns) !== count($storedNameservers)
            || array_diff($remoteDomain->ns, $storedNameservers) !== []
        ) {
            return new FailedDomainSubscriptionRepair(
                path: FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS,
                reason: 'remote_status_not_healthy_and_no_nameservers_set',
            );
        }

        return new FailedDomainSubscriptionRepair(
            path: FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY,
            reason: 'remote_exists_but_no_nameserver_repair_or_prevalidation_repair_applicable',
        );
    }

    /**
     * @throws FailedToFetchNameserversException
     * @throws DnsNamerverAlreadyAssignedException
     */
    public function repairMissingRemoteNameservers(Subscription $subscription, string $source): void
    {
        $domainDeployment = $subscription->domainDeployment;
        $logContext = $this->buildLogContext($subscription, $source, $domainDeployment);

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->logger->warning(
                'Skipping nameserver repair because no domain deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_deployment',
                    ],
                ],
            );

            return;
        }

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);
        if (! $dnsDeployment instanceof DnsDeployment) {
            $this->logger->warning(
                'Skipping nameserver repair because no DNS deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_dns_deployment',
                    ],
                ],
            );

            return;
        }

        $domain = $subscription->domain;
        if ($domain === null) {
            $this->logger->warning(
                'Skipping nameserver repair because no domain name was found on the subscription.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_name',
                    ],
                ],
            );

            return;
        }

        try {
            $remoteDomain = $this->rtrService->fetchDomain($domain);
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            if ($exception instanceof DomainDoesNotExistException || $exception->getMessage() === 'Not found.') {
                $this->logger->warning(
                    'Skipping nameserver repair because the remote domain no longer exists at RTR.',
                    $logContext + [
                        LoggingContextKeys::META => [
                            'reason' => 'remote_domain_missing',
                        ],
                    ],
                );

                return;
            }
            $this->logger->warning(
                'Skipping nameserver repair because RTR domain lookup failed.',
                $logContext + [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META      => [
                        'reason' => 'rtr_domain_lookup_failed',
                    ],
                ],
            );

            return;
        }

        $storedNameserverHostnames = $this->dnsDeploymentRepository->getNameserverHostnames($dnsDeployment);

        if ($storedNameserverHostnames === []) {
            $this->logger->warning(
                'Stored nameservers are missing while RTR has no nameservers, assigning nameservers first based of DNS.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'nameserver_type' => $dnsDeployment->nameserver_type->value,
                        'reason' => 'restore_missing_nameservers_locally_before_remote_repair',
                    ],
                ],
            );

            $nameserverAssigner = $this->nameserverAssignerFactory->createAssigner($dnsDeployment->nameserver_type);
            $storedNameserverHostnames = $nameserverAssigner->assign($dnsDeployment);
        }

        if ($storedNameserverHostnames === []) {
            $this->logger->warning(
                'Skipping nameserver repair because RTR has no nameservers and no stored nameservers could be assigned.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'nameserver_type' => $dnsDeployment->nameserver_type->value,
                        'reason' => 'missing_stored_nameservers',
                    ],
                ],
            );

            return;
        }

        try {
            $storedNameservers = $this->dnsDeploymentRepository->getNameservers($dnsDeployment);
            $this->rtrService->updateNameServers($domain, $storedNameservers);
            $refetchedRemoteDomain = $this->rtrService->fetchDomain($domain);
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Skipping nameserver repair follow-up because RTR nameserver update or refetch failed.',
                $logContext + [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'reason' => 'rtr_nameserver_update_or_refetch_failed',
                    ],
                ],
            );

            return;
        }

        $this->logger->info(
            'Refetched remote domain after nameserver repair.',
            $logContext + [
                LoggingContextKeys::META => [
                    'remote_statuses' => $refetchedRemoteDomain->status,
                    'remote_nameservers' => $refetchedRemoteDomain->ns,
                ],
            ],
        );

        $this->syncLocalStatusFromRemoteDomain(
            subscription: $subscription,
            domainDeployment: $domainDeployment,
            remoteDomain: $refetchedRemoteDomain,
            source: $source,
        );
    }

    public function restoreFromProcesses(
        Subscription $subscription,
        string $source,
        ProcessCollection $processCollection,
    ): void {
        $domainDeployment = $subscription->domainDeployment;
        $logContext = $this->buildLogContext(
            $subscription,
            $source,
            $domainDeployment instanceof DomainDeployment ? $domainDeployment : null,
        );

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->logger->warning(
                'Skipping pending-state restore because no domain deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_deployment',
                        'processes' => $processCollection->toArray(),
                    ],
                ],
            );

            return;
        }

        $domain = $subscription->domain;
        if ($domain === null) {
            $this->logger->warning(
                'Skipping pending-state restore because no domain name was found on the subscription.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_name',
                        'processes' => $processCollection->toArray(),
                    ],
                ],
            );

            return;
        }

        $logContext = $this->buildLogContext($subscription, $source, $domainDeployment);

        $currentOpenProcess = $this->findLatestOpenDomainProcess($processCollection);

        if (! $currentOpenProcess instanceof Process) {
            $this->logger->warning(
                'Skipping pending-state restore because RTR no longer has an open prevalidation process.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'processes' => $processCollection->toArray(),
                        'reason' => 'open_prevalidation_process_no_longer_present',
                    ],
                ],
            );

            return;
        }

        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();

        $domainDeployment->domain_status = RtrDomainStatus::PENDING_VALIDATION;

        $this->domainDeploymentResult(
            domainDeployment: $domainDeployment,
            payload: [
                'source' => $source,
                'repair' => 'restored_pending_from_open_prevalidation_process',
                'processes' => $processCollection->toArray(),
                'current_rtr_process_id' => $currentOpenProcess->id,
                'current_rtr_process_status' => $currentOpenProcess->status,
                'raw_process' => $currentOpenProcess->toArray(),
            ],
        );

        $this->logger->info(
            'Restored failed subscription to pending because RTR still has an open prevalidation process.',
            $logContext + [
                LoggingContextKeys::META => [
                    'current_rtr_process_id' => $currentOpenProcess->id,
                    'current_rtr_process_status' => $currentOpenProcess->status,
                ],
            ],
        );
    }

    public function syncStatusFromRemote(Subscription $subscription, string $source): void
    {
        $domainDeployment = $subscription->domainDeployment;
        $logContext = $this->buildLogContext(
            $subscription,
            $source,
            $domainDeployment instanceof DomainDeployment ? $domainDeployment : null,
        );

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->logger->warning(
                'Skipping status sync because no domain deployment was found.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_deployment',
                    ],
                ],
            );

            return;
        }

        $domain = $subscription->domain;
        if ($domain === null) {
            $this->logger->warning(
                'Skipping status sync because no domain name was found on the subscription.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'missing_domain_name',
                    ],
                ],
            );

            return;
        }

        $logContext = $this->buildLogContext($subscription, $source, $domainDeployment);

        try {
            $remoteDomain = $this->rtrService->fetchDomain($domain);
        } catch (DomainDoesNotExistException) {
            $this->logger->warning(
                'Skipping status sync because the remote domain no longer exists at RTR.',
                $logContext + [
                    LoggingContextKeys::META => [
                        'reason' => 'remote_domain_missing',
                    ],
                ],
            );

            return;
        } catch (Throwable $exception) { // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
            $this->logger->warning(
                'Skipping status sync because RTR domain lookup failed.',
                $logContext + [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'reason' => 'rtr_domain_lookup_failed',
                    ],
                ],
            );

            return;
        }

        $this->logger->info(
            'Fetched remote domain before local status sync.',
            $logContext + [
                LoggingContextKeys::META => [
                    'remote_statuses' => $remoteDomain->status,
                    'remote_nameservers' => $remoteDomain->ns,
                ],
            ],
        );

        $this->syncLocalStatusFromRemoteDomain(
            subscription: $subscription,
            domainDeployment: $domainDeployment,
            remoteDomain: $remoteDomain,
            source: $source,
        );
    }

    public function findLatestOpenDomainProcess(ProcessCollection $processes): ?Process
    {
        $openProcesses = [];

        foreach ($processes as $process) {
            if (! $process instanceof Process) {
                continue;
            }

            if ($this->isOpenRtrProcess($process)) {
                $openProcesses[] = $process;
            }
        }

        if ($openProcesses === []) {
            return null;
        }

        usort(
            $openProcesses,
            static fn (Process $left, Process $right): int => $right->createdDate <=> $left->createdDate,
        );

        return $openProcesses[0];
    }

    private function syncLocalStatusFromRemoteDomain(
        Subscription $subscription,
        DomainDeployment $domainDeployment,
        DomainDetailsDTO $remoteDomain,
        string $source,
    ): void {
        $logContext = $this->buildLogContext($subscription, $source, $domainDeployment);
        $domainStatus = $this->rtrService->getPrimaryDomainStatusFromDomainStatusList($remoteDomain->status);
        $technicalStatus = $this->rtrService->getTechnicalStatusFromDomainStatusList(
            $domainStatus instanceof RtrDomainStatus ? [$domainStatus->value] : $remoteDomain->status,
        );

        $subscription->technical_status = $technicalStatus;
        $subscription->save();

        $domainDeployment->domain_status = $domainStatus;

        $this->domainDeploymentResult(
            domainDeployment: $domainDeployment,
            payload: [
                'source' => $source,
                'repair' => 'synced_status_from_remote_domain',
                'remote_statuses' => $remoteDomain->status,
                'remote_nameservers' => $remoteDomain->ns,
                'remote_expiry_date' => $remoteDomain->expiryDate?->format('Y-m-d H:i:s'),
            ],
        );

        $this->logger->info(
            'Synced status from RTR.',
            $logContext + [
                LoggingContextKeys::META => [
                    'synced_technical_status' => $technicalStatus,
                    'synced_domain_status' => $domainStatus?->value,
                    'remote_statuses' => $remoteDomain->status,
                    'remote_nameservers' => $remoteDomain->ns,
                ],
            ],
        );
    }

    private function isOpenRtrProcess(Process $process): bool
    {
        if ($process->type !== 'domain') {
            return false;
        }

        return in_array($process->status, [
            ProcessStatusEnum::STATUS_NEW,
            ProcessStatusEnum::STATUS_VALIDATED,
            ProcessStatusEnum::STATUS_RUNNING,
            ProcessStatusEnum::STATUS_IN_DOUBT,
            ProcessStatusEnum::STATUS_SCHEDULED,
            ProcessStatusEnum::STATUS_SUSPENDED,
        ], true);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function buildBaseLogContext(Subscription $subscription, string $source): array
    {
        return [
            LoggingContextKeys::ONE_OFF_SCRIPT => $source,
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function buildLogContext(
        Subscription $subscription,
        string $source,
        ?DomainDeployment $domainDeployment = null,
    ): array {
        $logContext = $this->buildBaseLogContext($subscription, $source);

        if ($domainDeployment instanceof DomainDeployment) {
            $logContext[LoggingContextKeys::PROVISIONING_ID] = $domainDeployment->id;
        }

        return $logContext;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function domainDeploymentResult(
        DomainDeployment $domainDeployment,
        array $payload,
    ): void {
        $domainDeployment->last_result = json_encode($payload, JSON_THROW_ON_ERROR);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->save();
    }
}
