<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\RetroFixDeliverdSsl;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrSslService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class RetroFixDeliveredSslJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly bool $isDryRun,
    ) {
        parent::__construct();
    }

    public function handle(
        LoggerInterface $logger,
        RtrSslService $rtrSslService,
    ): void {
        $sslDeployment = $this->subscription->sslDeployment;

        if (
            $this->subscription->domain === null
            || $sslDeployment === null
            || $sslDeployment->status === 'Installation error.'
        ) {
            $logger->info(
                sprintf(
                    'Skipping subscription %d due to missing domain or SSL deployment or installation error.',
                    $this->subscription->id,
                ),
                [
                    LoggingContextKeys::ONE_OFF_SCRIPT => NovaRetroFixDeliverdSslAction::SLUG,
                    LoggingContextKeys::META => [
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                        LoggingContextKeys::META => [
                            'dry-run' => $this->isDryRun,
                            'ssl_deployment' => $sslDeployment?->id,
                            'ssl_status' => $sslDeployment?->status,
                            'ssl_domain' => $this->subscription->domain,
                        ],
                    ],
                ],
            );

            return;
        }

        $processes = $rtrSslService->getLatestSslProcess($this->subscription->domain);
        $latestProcess = $processes->entities[0] ?? null;
        $hasCompletedSsl = $latestProcess !== null && $latestProcess->status === ProcessStatusEnum::STATUS_COMPLETED;

        $logger->info(
            sprintf(
                'Checked SSL deployment for with domain %s, ssl completed at RTR: %s',
                $this->subscription->domain,
                $hasCompletedSsl ? 'yes' : 'no',
            ),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => NovaRetroFixDeliverdSslAction::SLUG,
                LoggingContextKeys::META => [
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::META => [
                        'dry-run' => $this->isDryRun,
                        'ssl_deployment' => $sslDeployment->id,
                        'ssl_status' => $sslDeployment->status,
                        'ssl_domain' => $this->subscription->domain,
                        'latest_process_status' => $latestProcess?->status,
                    ],
                ],
            ],
        );

        if ($hasCompletedSsl && ! $this->isDryRun) {
            $sslDeployment->last_result = 'Set to OK because RTR has a completed SSL process for this domain.';
            $sslDeployment->last_result_received = CarbonImmutable::now();
            $sslDeployment->save();

            $this->subscription->technical_status = TechnicalStatus::OK->value;
            $this->subscription->save();
        }
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::ONE_TIME_SCRIPTS;
    }
}
