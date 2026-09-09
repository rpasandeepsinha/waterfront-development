<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Subscriptions\Jobs\ExpireSubscriptionJob;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[AsCommand(name: 'subscriptions:expire')]
#[Description('Command to expire subscriptions that passed their end_date')]
class AdministrativelyExpireSubscriptions extends AbstractCommand
{
    public function handle(SubscriptionRepository $subscriptionRepository, Dispatcher $jobDispatcher): int
    {
        foreach ($subscriptionRepository->getAllExpiringSubscriptions(new CarbonImmutable()) as $subscription) {
            $job = new ExpireSubscriptionJob($subscription);
            $jobDispatcher->dispatch($job);
        }

        return self::SUCCESS;
    }
}
