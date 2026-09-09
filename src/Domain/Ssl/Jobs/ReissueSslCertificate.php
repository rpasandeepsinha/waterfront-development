<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Queue\InteractsWithQueue;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ReissueSslCertificate extends AbstractQueueableJob
{
    use InteractsWithQueue;

    public function __construct(
        public readonly SslDeployment $sslDeployment
    ) {
        parent::__construct();
    }

    public function failed(Throwable $exception): void
    {
        $subscription = $this->sslDeployment->subscription;
        $subscription->technical_status = TechnicalStatus::FAILED->value;
        $subscription->save();

        $this->sslDeployment->last_result = json_encode([
            'message' => 'Reissue failed with exception.',
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ], JSON_THROW_ON_ERROR);

        $this->sslDeployment->last_result_received = CarbonImmutable::now();
        $this->sslDeployment->save();
    }

    public function handle(
        CustomerSharedSslService $customerSharedSslService,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->sslDeployment->subscription;

        $reissueResult = $customerSharedSslService->reissue($this->sslDeployment);

        $reissueResultData = [
            'reissue_result_status'        => $reissueResult->getStatus(),
            'reissue_result_reason'        => $reissueResult->getReason(),
            'reissue_result_error_code'    => $reissueResult->getErrorCode(),
            'reissue_result_error_message' => $reissueResult->getErrorMessage(),
        ];

        $context = [
            LoggingContextKeys::QUEUE_JOB_ID => $this->job?->getJobId(),
            LoggingContextKeys::QUEUE_NAME => $this->getQueueName()->value,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            LoggingContextKeys::PROVISIONING_ID   => $this->sslDeployment->id,
            LoggingContextKeys::SUBSCRIPTION_UUID => $this->sslDeployment->subscription_uuid,
            LoggingContextKeys::META              => $reissueResultData,
        ];

        if ($reissueResult->getErrorCode() !== null || $reissueResult->getErrorMessage() !== null) {
            $subscription->technical_status = TechnicalStatus::FAILED->value;
            $subscription->save();

            $this->sslDeployment->last_result = json_encode($reissueResultData, JSON_THROW_ON_ERROR);
            $this->sslDeployment->last_result_received = CarbonImmutable::now();
            $this->sslDeployment->save();

            $logger->error(
                'ReissueSslCertificate reissue error result',
                $context
            );
            return;
        }

        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();

        $this->sslDeployment->last_result = 'ReissueSslCertificate process completed successfully, now waiting for RTR';
        $this->sslDeployment->last_result_received = CarbonImmutable::now();
        $this->sslDeployment->save();

        $logger->info(
            'ReissueSslCertificate reissue result',
            $context
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::PARTNER_SSL;
    }
}
