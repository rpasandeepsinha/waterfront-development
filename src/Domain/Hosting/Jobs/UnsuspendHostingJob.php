<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Illuminate\Container\Container;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Email\Actions\SendSubscriptionUnSuspendedMailAction;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class UnsuspendHostingJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly HostingDeployment $hostingDeployment,
        private readonly bool $sendEmailOnSuccess,
    ) {
        parent::__construct();
    }

    public function handle(
        HostingServiceFactory $hostingServiceFactory,
        SendSubscriptionUnSuspendedMailAction $sendSubscriptionUnSuspendedMailAction,
        StoreAuditLogAction $storeAuditLogAction,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->hostingDeployment->subscription;
        $logger->info(
            'Unsuspending subscription with id {Subscription_id}',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ],
        );

        try {
            $hostingDriver = $hostingServiceFactory->defaultDriver();
            $this->hostingDeployment->subscription->update([
                'technical_status' => TechnicalStatus::UNSUSPENDING->value,
            ]);

            $hostingDriver->unsuspend($this->hostingDeployment);

            //Create audit log of success and save new admin/technical statusses accordingly.
            $storeAuditLogAction->execute(
                AuditLogEvent::UNSUSPENSION,
                HostingDeployment::class,
                $this->hostingDeployment->id,
            );

            if ($this->sendEmailOnSuccess) {
                $this->informCustomerViaEmail($sendSubscriptionUnSuspendedMailAction, $subscription, $logger);
            }
        } catch (NotImplementedException|InvalidArgumentException $exception) {
            $this->failed($exception);

            return;
        } catch (PleskClientException|JsonException $exception) {
            $logger->error((string) $exception);

            if ($this->attempts() > $this->tries) {
                $this->fail($exception);
            } else {
                $this->release(60);
            }

            return;
        }

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->hostingDeployment->subscription->technical_status = TechnicalStatus::UNSUSPENSION_FAILED->value;
        $this->hostingDeployment->subscription->save();
        $container = Container::getInstance();
        $subscriptionMetadataService = $container->make(SubscriptionMetadataService::class);
        $subscriptionMetadataService->assignCategory(
            $this->hostingDeployment->subscription,
            SubscriptionCategory::UNSUSPENSION,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }

    private function informCustomerViaEmail(
        SendSubscriptionUnSuspendedMailAction $sendSubscriptionUnSuspendedMailAction,
        Subscription $subscription,
        LoggerInterface $logger,
    ): void {
        $logger->info(
            'Unsuspension for subscription with {Subscription_id} successfully, informing the customer...',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ],
        );
        $sendSubscriptionUnSuspendedMailAction->execute($subscription);
    }
}
