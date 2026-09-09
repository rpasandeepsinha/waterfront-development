<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\ManualProvisioning\Services\ManualProvisioningService;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[AsCommand(name: 'manual-subscriptions:send-termination-reminder')]
#[Description('Command for sending a support reminder for termination of a manualsubscription')]
class SendManualTerminationReminder extends AbstractCommand
{
    public function handle(
        SubscriptionRepository $subscriptionRepository,
        ManualProvisioningService $manualProvisioningService
    ): int {
        $subscriptions = $subscriptionRepository->getNotTerminatedManualSubscriptions();

        if ($subscriptions->count() > 0) {
            $this->line('Sending ' . $subscriptions->count() . ' notifications for manual subscriptions');

            foreach ($subscriptions as $subscription) {
                $manualProvisioningService->sendTerminationReminderNotification($subscription);
            }

            return self::SUCCESS;
        }

        $this->line('There where no notifications to send.');
        return self::SUCCESS;
    }
}
