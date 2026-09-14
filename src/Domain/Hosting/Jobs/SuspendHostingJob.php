<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;
use Waterfront\Domain\AuditLogs\Actions\StoreAuditLogAction;
use Waterfront\Domain\AuditLogs\Enums\AuditLogEvent;
use Waterfront\Domain\Email\Actions\SendSubscriptionSuspendedMailAction;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class SuspendHostingJob extends AbstractQueueableJob
{
    public int $tries = 3;

    public function __construct(
        private readonly HostingDeployment $hostingDeployment,
        private readonly bool $sendMailAfterSuspensionSuccess,
    ) {
        parent::__construct();
    }

    public function handle(
        HostingServiceFactory $hostingServiceFactory,
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        StoreAuditLogAction $storeAuditLogAction,
        LoggerInterface $logger,
    ): void {
        $subscription = $this->hostingDeployment->subscription;
        $subscription->technical_status = TechnicalStatus::SUSPENDING->value;
        $subscription->suspended_at = CarbonImmutable::now();
        $subscription->save();

        $logger->info(sprintf('Suspending subscription with uuid: %s', $subscription->uuid));
        try {
            $hostingDriver = $hostingServiceFactory->defaultDriver();
            $hostingDriver->suspend($this->hostingDeployment);

            //Create audit log of success and save new admin/technical statuses accordingly.
            $storeAuditLogAction->execute(
                AuditLogEvent::SUSPENSION,
                HostingDeployment::class,
                $this->hostingDeployment->id,
            );

            $subscription->technical_status = TechnicalStatus::SUSPENDED->value;
            $subscription->save();

            if ($this->sendMailAfterSuspensionSuccess) {
                $this->informCustomer(
                    $subscription,
                    $sendSubscriptionSuspendedMailAction,
                    $logger,
                );
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

        $subscription->technical_status = TechnicalStatus::SUSPENDED->value;
        $subscription->save();
    }

    public function failed(Throwable $exception): void
    {
        $storeAuditLogAction = new StoreAuditLogAction();
        $storeAuditLogAction->execute(
            AuditLogEvent::SUSPENSION,
            HostingDeployment::class,
            $this->hostingDeployment->id,
            newValues: [$exception->getMessage()],
        );
        $this->hostingDeployment->subscription->technical_status = TechnicalStatus::SUSPENSION_FAILED->value;
        $this->hostingDeployment->subscription->save();
        $container = Container::getInstance();
        $subscriptionMetadataService = $container->make(SubscriptionMetadataService::class);
        $subscriptionMetadataService->assignCategory(
            $this->hostingDeployment->subscription,
            SubscriptionCategory::SUSPENSION,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }

    private function informCustomer(
        Subscription $subscription,
        SendSubscriptionSuspendedMailAction $sendSubscriptionSuspendedMailAction,
        LoggerInterface $logger,
    ): void {
        $logger->info(sprintf(
            'Suspension for subscription with uuid: %s successfully, informing the customer..',
            $subscription->uuid,
        ));
        $sendSubscriptionSuspendedMailAction->execute($subscription);
    }
}
