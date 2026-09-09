<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingDowngradeExecutor;
use Waterfront\Domain\Subscriptions\DTO\SubscriptionChangeResult;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class DowngradeHostingJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly SubscriptionMutation $subscriptionMutation,
        private readonly SubscriptionChange $subscriptionChange,
    ) {
        parent::__construct();
    }

    public function handle(
        LoggerInterface $logger,
        HostingDowngradeExecutor $hostingDowngradeExecutor,
    ): void {
        $logger->info(
            'Start downgrade hosting job: {subscription.uuid}',
            [
            LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
            LoggingContextKeys::META => [
                'subscription_change_id' => $this->subscriptionChange->id,
                'subscription_mutation_id' => $this->subscriptionMutation->id,
            ],
        ]
        );

        $deployment = $this->subscription->hostingDeployment;
        if (! $deployment instanceof HostingDeployment) {
            $logger->info(
                'subscription: {subscription.uuid} has no hostingDeployment',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $this->subscription->uuid,
                    LoggingContextKeys::META => [
                        'subscription_change_id' => $this->subscriptionChange->id,
                        'subscription_mutation_id' => $this->subscriptionMutation->id,
                    ],
                ]
            );

            return;
        }

        $this->subscriptionChange->status = SubscriptionChangeStatus::INPROGRESS;
        $this->subscriptionChange->save();

        $this->subscriptionMutation->processed_technical_at = CarbonImmutable::now();
        $this->subscriptionMutation->save();

        $result = $hostingDowngradeExecutor->execute(
            $this->subscription,
            $deployment,
            $this->subscription->product,
            $this->subscriptionMutation->product,
        );

        if ($result->status === SubscriptionChangeResult::STATUS_ERROR) {
            $this->failed(new Exception($result->errorMessage ?? 'Hosting change failed, original error message empty.'), $deployment);

            return;
        }

        $this->subscriptionChange->status = SubscriptionChangeStatus::COMPLETED;
        $this->subscriptionChange->completed_at = CarbonImmutable::now();
        $this->subscriptionChange->save();
    }

    public function failed(Throwable $exception, HostingDeployment $hostingDeployment): void
    {
        $hostingDeployment->last_created_result_received = CarbonImmutable::now();
        $json = json_encode([ 'message' => $exception->getMessage(), 'code' => $exception->getCode()], JSON_THROW_ON_ERROR);
        $hostingDeployment->last_created_result = $json;
        $hostingDeployment->save();

        $hostingDeployment->subscription->technical_status = TechnicalStatus::ERROR->value;
        $hostingDeployment->subscription->save();

        $container = Container::getInstance();
        $subscriptionMetadataService = $container->make(SubscriptionMetadataService::class);
        $subscriptionMetadataService->assignCategory(
            $this->subscription,
            SubscriptionCategory::PRODUCT_CHANGE,
        );

        $this->subscriptionChange->status = SubscriptionChangeStatus::EXECUTION_FAILED;
        $this->subscriptionChange->failure_code = (int) $exception->getCode();
        $this->subscriptionChange->failure_message = $exception->getMessage();
        $this->subscriptionChange->save();
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
