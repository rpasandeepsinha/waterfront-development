<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\NotificationHandlers;

use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use RealtimeRegister\Domain\Process;
use UnexpectedValueException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus as LocalDomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\DNS\Actions\ValidateDnsPropagationAndRetry;
use Waterfront\Domain\Provision\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\Provision\DNS\Exceptions\NameserversNotFoundException;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\RtrClient\Enums\RtrValidationError;
use Waterfront\Infra\RtrClient\Enums\SubjectStatusType;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Job\SetNameserversForDomainJob;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

class DomainNotificationHandler
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly RtrService $rtrService,
        private readonly DomainProviderHistory $domainProviderHistory,
        private readonly RtrErrorParseService $rtrErrorParseService,
        private readonly ValidateDnsPropagationAndRetry $dnsRetryAction,
        private readonly LoggerInterface $logger,
        private readonly NotificationHelper $notificationHelper,
        private readonly Dispatcher $busDispatcher,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
    ) {
    }

    /**
     * @throws DnsDeploymentNotFoundException
     * @throws NameserversNotFoundException
     * @throws Exception
     */
    public function handle(Notification $notification, RtrResponseLog $rtrResponseLog): void
    {
        if (! $this->supportsDomainNotification($notification)) {
            return;
        }

        $domainName = $this->notificationHelper->extractDomainName($notification);

        if ($domainName === null) {
            $this->skip('no usable domainName', $rtrResponseLog, $domainName);

            return;
        }

        if ($this->isFailedCreateDomainNotification($notification)) {
            $this->updateSubscriptionFromNotification(
                rtrResponseLog: $rtrResponseLog,
                domainName: $domainName,
                status: TechnicalStatus::FAILED->value,
                message: $notification->message,
                sendMail: true,
            );

            return;
        }

        $rtrError = $this->rtrErrorParseService->getRtrErrorFromMessage($notification->message);

        if ($rtrError === RtrValidationError::REGISTRY_REQUIREMENTS_NOT_MET) {
            $this->dnsRetryAction->execute($domainName);

            return;
        }

        try {
            $subscription = $this->subscriptionRepository->getSubscriptionByDomainAndGroup(
                $domainName,
                ProductGroupType::EXTENSION,
            );
        } catch (ModelNotFoundException $exception) {
            $this->logger->warning('Unable to find domain subscription for domain {domain.name}', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::DOMAIN_NAME => $domainName,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            ]);

            return;
        }

        $subscription->loadMissing('domainDeployment');
        $domainDeployment = $subscription->domainDeployment;
        $isCreateDomainNotification = $this->notificationHelper->isCreateDomainNotification($notification);

        try {
            $remoteDomain = $this->rtrService->fetchDomain($domainName);
        } catch (DomainDoesNotExistException $exception) {
            if (
                ! $isCreateDomainNotification
                && $subscription->technical_status === TechnicalStatus::PENDING->value
                && $domainDeployment instanceof DomainDeployment
                && $this->rtrService->findOpenPrevalidationProcessForDomain($domainName) instanceof Process
            ) {
                $domainDeployment->domain_status = DomainStatus::PENDING_VALIDATION;
                $domainDeployment->save();

                $this->updateSubscriptionFromNotification(
                    rtrResponseLog: $rtrResponseLog,
                    domainName: $domainName,
                    status: TechnicalStatus::PENDING->value,
                    message: $notification->message,
                    sendMail: false,
                );

                return;
            }

            throw $exception;
        }

        $primaryDomainStatus = $this->rtrService->getPrimaryDomainStatusFromDomainStatusList($remoteDomain->status);

        if ($primaryDomainStatus === null && $this->notificationHelper->isCreateDomainNotification($notification)) {
            throw new UnexpectedValueException('RTR returned no recognized domain status');
        }

        $wasPendingValidation =
            $subscription->technical_status === TechnicalStatus::PENDING->value
            && $domainDeployment instanceof DomainDeployment
            && $domainDeployment->domain_status === DomainStatus::PENDING_VALIDATION;

        if ($domainDeployment instanceof DomainDeployment && $primaryDomainStatus instanceof DomainStatus) {
            $this->domainDeploymentRepository->setDomainStatus($domainDeployment, $primaryDomainStatus);
        }

        $remoteStatuses = $remoteDomain->status;
        $hasPendingValidation = in_array(DomainStatus::PENDING_VALIDATION->value, $remoteStatuses, true);
        $hasInactiveStatus = in_array(DomainStatus::INACTIVE->value, $remoteStatuses, true);
        $hasOkStatus = in_array(DomainStatus::OK->value, $remoteStatuses, true);

        $status = $this->rtrService->getTechnicalStatusFromDomainStatusList($remoteStatuses);
        $sendMail = true;
        $shouldDispatchNameserverUpdate = false;

        if ($hasPendingValidation) {
            $status = TechnicalStatus::PENDING->value;
            $sendMail = false;
        } elseif ($hasInactiveStatus) {
            $status = TechnicalStatus::PENDING->value;
            $sendMail = false;
            $shouldDispatchNameserverUpdate = $this->getLocalNameserverHostnames($domainName) !== [];

            if (! $shouldDispatchNameserverUpdate) {
                $this->logger->warning('RTR domain inactive without local nameservers', [
                    LoggingContextKeys::DOMAIN_NAME => $domainName,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                ]);
            }

            if ($wasPendingValidation) {
                $this->logPrevalidationCompletion($domainName, DomainStatus::INACTIVE);
            }
        } elseif ($hasOkStatus) {
            if ($isCreateDomainNotification) {
                $status = LocalDomainStatus::ACTIVE->value;
            }

            if ($wasPendingValidation) {
                $shouldDispatchNameserverUpdate = $this->localNameserversDifferFromRemote($domainName, $remoteDomain);

                $this->logPrevalidationCompletion($domainName, DomainStatus::OK);
            }
        }

        $this->updateSubscriptionFromNotification(
            rtrResponseLog: $rtrResponseLog,
            domainName: $domainName,
            status: $status,
            message: $notification->message,
            sendMail: $sendMail,
        );

        if ($shouldDispatchNameserverUpdate) {
            $this->logger->info('Dispatching RTR nameserver update', [
                LoggingContextKeys::DOMAIN_NAME => $domainName,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            ]);

            $this->busDispatcher->dispatch(new SetNameserversForDomainJob($domainName));
        }
    }

    private function logPrevalidationCompletion(string $domainName, DomainStatus $domainStatus): void
    {
        $this->logger->info('RTR prevalidation domain completed', [
            LoggingContextKeys::DOMAIN_NAME => $domainName,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            LoggingContextKeys::META => [
                'domain_status' => $domainStatus->value,
            ],
        ]);
    }

    private function updateSubscriptionFromNotification(
        RtrResponseLog $rtrResponseLog,
        string $domainName,
        string $status,
        string $message,
        bool $sendMail,
    ): void {
        try {
            $this->domainProviderHistory->saveHistory(
                $rtrResponseLog,
                ProviderSlug::REALTIME_REGISTER,
                $domainName,
                $status,
                $message,
            );
            $this->subscriptionService->updateSubscriptionStatus($domainName, $status, $message, $sendMail);
        } catch (ModelNotFoundException $exception) {
            $this->logger->warning('Unable to find domain subscription for domain {domain.name}', [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::DOMAIN_NAME => $domainName,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            ]);
        }
    }

    private function localNameserversDifferFromRemote(string $domainName, DomainDetailsDTO $remoteDomain): bool
    {
        $localNameservers = $this->normalizeNameservers($this->getLocalNameserverHostnames($domainName));

        if ($localNameservers === []) {
            return false;
        }

        return $localNameservers !== $this->normalizeNameservers($remoteDomain->ns);
    }

    /**
     * @return string[]
     */
    private function getLocalNameserverHostnames(string $domainName): array
    {
        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomain($domainName);

        if ($dnsDeployment === null) {
            return [];
        }

        return $this->dnsDeploymentRepository->getNameserverHostnames($dnsDeployment);
    }

    /**
     * @param array<array-key, string> $nameservers
     *
     * @return string[]
     */
    private function normalizeNameservers(array $nameservers): array
    {
        $normalizedNameservers = array_map(
            static fn (string $nameserver): string => strtolower(rtrim($nameserver, '.')),
            $nameservers,
        );

        sort($normalizedNameservers);

        return $normalizedNameservers;
    }

    private function supportsDomainNotification(Notification $notification): bool
    {
        return (
            $this->notificationHelper->isCreateDomainNotification($notification)
            || $this->notificationHelper->isUpdateDomainNotification($notification)
            || $this->notificationHelper->isDeleteDomainNotification($notification)
        );
    }

    private function isFailedCreateDomainNotification(Notification $notification): bool
    {
        return (
            $this->notificationHelper->isCreateDomainNotification($notification)
            && $notification->subjectStatus === SubjectStatusType::FAILED->value
        );
    }

    private function skip(string $reason, RtrResponseLog $rtrResponseLog, ?string $domainName): void
    {
        $this->logger->notice('Skipping RTR notification; details stored', [
            LoggingContextKeys::DOMAIN_NAME => $domainName,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            LoggingContextKeys::META => [
                'reason' => $reason,
                'rtr_response_log_id' => $rtrResponseLog->id,
            ],
        ]);
    }
}
