<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Subscriptions\Jobs\TerminateSubscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[AsCommand(name: 'subscriptions:terminate')]
#[Description('Command for terminating all expired subscriptions')]
class TerminateSubscriptions extends AbstractCommand
{
    public function handle(
        SubscriptionRepository $subscriptionRepository,
        Dispatcher $dispatcher,
        LoggerInterface $logger,
        SubscriptionChangeService $changeService,
    ): int {
        $subscriptions = $subscriptionRepository->getAllDueForTermination();

        $logger->info('TerminateSubscriptions, found #' . $subscriptions->count() . ' subscriptions');

        foreach ($subscriptions as $subscription) {
            $logger->info('Dispatching terminate subscription job for subscription {subscription.id}: {domain.name}', [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]);

            foreach ($subscription->children as $child) {
                if ($changeService->shouldDowngradeSubscriptionWithParent($child)) {
                    $changeService->downgradeCanceled($child);
                }
            }

            $dispatcher->dispatch(new TerminateSubscription($subscription));
        }

        $logger->info('Finished with dispatching terminate subscription');

        return self::SUCCESS;
    }
}
