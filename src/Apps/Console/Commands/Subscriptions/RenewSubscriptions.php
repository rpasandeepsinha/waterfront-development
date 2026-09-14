<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Subscriptions\Jobs\RenewSubscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[AsCommand(name: 'subscriptions:renew')]
#[Description('Command to renew all given subscriptions')]
class RenewSubscriptions extends AbstractCommand
{
    public function handle(SubscriptionRepository $subscriptionRepository, Dispatcher $jobDispatcher): int
    {
        $subscriptions = $subscriptionRepository->getAllDueForRenewal();

        $this->line('Renewing ' . $subscriptions->count() . ' subscriptions');

        foreach ($subscriptions as $subscription) {
            $this->line(sprintf(
                'Dispatching subscription renewal for %s (%s)',
                $subscription->domain ?? '',
                $subscription->uuid,
            ));

            $jobDispatcher->dispatch(new RenewSubscription($subscription));
        }

        $this->line('Finished with renewing the subscriptions');

        return self::SUCCESS;
    }
}
