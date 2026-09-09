<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services\NotificationHandlers;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Enum\StatusEnum;
use RealtimeRegister\Domain\Notification;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use RealtimeRegister\RealtimeRegister;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Job\DownloadCertificate;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class CertificateNotificationHandler
{
    private const string STATUS_MESSAGE_CERTIFICATE_READY = 'Certificate ready to be downloaded.';
    private const string STATUS_MESSAGE_CERTIFICATE_PENDING = 'Certificate request pending validation.';
    private const string STATUS_MESSAGE_CERTIFICATE_REQUIRES_ATTENTION = 'Certificate request requires attention.';
    private const array ACTIVE_PROCESS_STATUSES = [
        ProcessStatusEnum::STATUS_NEW,
        ProcessStatusEnum::STATUS_VALIDATED,
        ProcessStatusEnum::STATUS_RUNNING,
    ];

    public function __construct(
        private readonly RealtimeRegister $realtimeRegister,
        private readonly DeploymentRepository $sslRepository,
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly NotificationHelper $notificationHelper,
    ) {
    }

    /**
     * @throws JsonException
     */
    public function handle(Notification $notification): void
    {
        $this->assertCertificateNotification($notification);

        $this->logger->info(
            sprintf(
                'Handling RTR notification #%d for SSL certificate request event.',
                $notification->id
            ),
            [
                LoggingContextKeys::META => [
                    'notification' => json_encode($notification->toArray()),
                ],
            ]
        );

        $certificateId = $this->extractCertificateId($notification);

        if ($this->isCertificateReady($notification->message, $certificateId)) {
            Assert::integer($certificateId);
            $sslDeployment = $this->resolveCertificateReadyDeployment($notification, $certificateId);

            if ($sslDeployment === null) {
                return;
            }

            $this->handleCertificateReady(
                sslDeployment: $sslDeployment,
                rtrMessage: $notification->message,
                certificateId: $certificateId
            );
            return;
        }

        $processId = (string) $notification->process;
        $sslDeployment = $this->sslRepository->findByRequestIdWithTrashed($processId);

        // If it is not ready yet, fetch the current process info containing validation state and notes.
        $this->handleCertificateNotReady(
            sslDeployment: $sslDeployment,
            notification: $notification
        );
    }

    private function assertCertificateNotification(Notification $notification): void
    {
        if ($this->notificationHelper->isCertificateRequestNotification($notification)) {
            return;
        }

        throw new InvalidArgumentException(
            'Cannot handle notification because it is not a certificate request notification.'
        );
    }

    private function extractCertificateId(Notification $notification): ?int
    {
        if (is_int($notification->certificateId)) {
            return $notification->certificateId;
        }

        $payload = $notification->payload ?? null;

        if (! is_array($payload)) {
            return null;
        }

        $certificateId = Arr::get($payload, 'certificateId');
        Assert::nullOrInteger($certificateId);

        return $certificateId;
    }

    private function isCertificateReady(string $message, ?int $certificateId): bool
    {
        return strtolower($message) === 'certificate request completed'
            && is_int($certificateId);
    }

    private function resolveCertificateReadyDeployment(Notification $notification, int $certificateId): ?SslDeployment
    {
        if ($notification->process !== null) {
            try {
                return $this->sslRepository->findByRequestIdWithTrashed((string) $notification->process);
            } catch (ModelNotFoundException) {
                // @ignoreException fall back to RTR certificate lookup when local request_id is missing.
            }
        }

        $processId = $notification->process;

        if ($processId === null) {
            $this->logger->warning('Skipping RTR SSL notification', [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                LoggingContextKeys::META => [
                    'reason' => 'no process',
                    'rtr_notification_id' => $notification->id,
                    'certificate_id' => $certificateId,
                ],
            ]);

            return null;
        }

        try {
            $certificate = $this->realtimeRegister->certificates->listCertificates(
                limit: 1,
                offset: 0,
                parameters: [
                    'process' => $processId,
                    'status' => StatusEnum::STATUS_ACTIVE,
                    'order' => '-startDate',
                ],
            )->offsetGet(0);
        } catch (RealtimeRegisterClientException $exception) {
            $this->logger->warning('RTR certificate lookup failed', [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::META => [
                    'rtr_notification_id' => $notification->id,
                    'rtr_process_id' => $processId,
                    'certificate_id' => $certificateId,
                ],
            ]);

            return null;
        }

        if ($certificate === null) {
            $this->logger->warning('Skipping RTR SSL notification', [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                LoggingContextKeys::META => [
                    'reason' => 'no active certificate found for process',
                    'rtr_notification_id' => $notification->id,
                    'rtr_process_id' => $processId,
                    'certificate_id' => $certificateId,
                ],
            ]);
            return null;
        }

        $domainName = $certificate->domainName;
        $deploymentCandidates = $this->sslRepository->getPendingRtrDeploymentCandidatesByDomain($domainName);

        if ($deploymentCandidates->count() !== 1) {
            $this->logger->warning('Skipping RTR SSL notification', [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                LoggingContextKeys::DOMAIN_NAME => $domainName,
                LoggingContextKeys::META => [
                    'reason' => 'deployment candidate count mismatch',
                    'deployment_candidate_count' => $deploymentCandidates->count(),
                    'rtr_notification_id' => $notification->id,
                    'rtr_process_id' => $processId,
                    'certificate_id' => $certificateId,
                ],
            ]);
            return null;
        }

        $sslDeployment = $deploymentCandidates->first();
        Assert::isInstanceOf($sslDeployment, SslDeployment::class);

        $sslDeployment->request_id = $processId;
        $sslDeployment->save();

        $this->logger->info('Recovered RTR SSL notification', [
            LoggingContextKeys::DOMAIN_NAME => $domainName,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
            LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
            LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
            LoggingContextKeys::META => [
                'rtr_notification_id' => $notification->id,
                'rtr_process_id' => $processId,
                'certificate_id' => $certificateId,
            ],
        ]);

        return $sslDeployment;
    }

    /**
     * @throws JsonException
     */
    private function handleCertificateReady(
        SslDeployment $sslDeployment,
        string $rtrMessage,
        int $certificateId
    ): void {
        $payload = [
            'certificate_status' => self::STATUS_MESSAGE_CERTIFICATE_READY,
            'message' => $rtrMessage,
        ];

        $this->updateSslDeployment(
            sslDeployment: $sslDeployment,
            payload: $payload,
            technicalStatus: TechnicalStatus::OK->value,
            certificateId: $certificateId
        );

        $this->logger->info(
            sprintf(
                'Certificate for SSL deployment #%d ready to be downloaded: #%d',
                $sslDeployment->id,
                $certificateId
            )
        );

        $this->dispatcher->dispatch(new DownloadCertificate($sslDeployment));
    }

    /**
     * @throws JsonException
     * @throws Exception
     */
    private function handleCertificateNotReady(
        SslDeployment $sslDeployment,
        Notification $notification
    ): void {
        $this->logger->info(
            sprintf(
                'SSL request still pending for SSL deployment #%s',
                $sslDeployment->id
            )
        );

        $processId = $notification->process;
        Assert::notNull($processId, 'RTR notification is missing a process id');

        // You can't fetch process "info" unless the process is in an "active" state.
        // You do always can fetch process "data", so we use that to check the state first.
        $processData = $this->realtimeRegister->processes->get($processId);
        $processStatus = $processData->status;
        $processIsActive = in_array($processStatus, self::ACTIVE_PROCESS_STATUSES, true);

        if (! $processIsActive) {
            $this->handleInactiveProcess(
                sslDeployment: $sslDeployment,
                processStatus: $processStatus,
                processData: $processData
            );
            return;
        }

        $this->handleActiveProcess(
            sslDeployment: $sslDeployment,
            processId: $processId
        );
    }

    /**
     * @throws JsonException
     */
    private function handleInactiveProcess(
        SslDeployment $sslDeployment,
        string $processStatus,
        Process $processData,
    ): void {
        if ($processStatus === ProcessStatusEnum::STATUS_COMPLETED) {
            $payload = [
                'certificate_status' => self::STATUS_MESSAGE_CERTIFICATE_READY,
                'message' => $processData,
            ];

            $this->updateSslDeployment(
                sslDeployment: $sslDeployment,
                payload: $payload,
                technicalStatus: TechnicalStatus::OK->value
            );
            return;
        }

        $payload = [
            'certificate_status' => self::STATUS_MESSAGE_CERTIFICATE_REQUIRES_ATTENTION,
            'message' => $processData,
        ];

        $this->updateSslDeployment(
            sslDeployment: $sslDeployment,
            payload: $payload,
            technicalStatus: TechnicalStatus::FAILED->value
        );
    }

    /**
     * @throws JsonException
     */
    private function handleActiveProcess(SslDeployment $sslDeployment, int $processId): void
    {
        $processInfo = $this->realtimeRegister->processes->info($processId)->toArray();
        $requiresAttention = (bool) ($processInfo['requiresAttention'] ?? false);

        $payload = [
            'certificate_status' => $requiresAttention
                ? self::STATUS_MESSAGE_CERTIFICATE_REQUIRES_ATTENTION
                : self::STATUS_MESSAGE_CERTIFICATE_PENDING,
            'message' => $processInfo,
        ];
        $this->updateSslDeployment(
            sslDeployment: $sslDeployment,
            payload: $payload,
            technicalStatus: $requiresAttention ? TechnicalStatus::ERROR->value : TechnicalStatus::OK->value
        );
    }

    /**
     * @param array<mixed> $payload
     *
     * @throws JsonException
     */
    private function updateSslDeployment(
        SslDeployment $sslDeployment,
        array $payload,
        ?string $technicalStatus,
        ?int $certificateId = null,
    ): void {
        $sslDeployment->last_result_received = CarbonImmutable::now();
        $sslDeployment->last_result = json_encode($payload, JSON_THROW_ON_ERROR);

        if ($certificateId !== null) {
            $sslDeployment->certificate_id = $certificateId;
        }

        $sslDeployment->save();

        $sslDeployment->subscription->technical_status = $technicalStatus;
        $sslDeployment->subscription->save();
    }
}
