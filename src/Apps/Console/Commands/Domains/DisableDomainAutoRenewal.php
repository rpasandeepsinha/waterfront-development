<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Domains;

use Illuminate\Console\Attributes\Description;
use Illuminate\Contracts\Bus\Dispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Domains\Jobs\DisableDomainAutoRenewal as DisableDomainAutoRenewalJob;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[AsCommand(name: 'domain-subscriptions:disable-autorenewal')]
#[Description('Disable autorenewal for all cancelled domain subscriptions.')]
class DisableDomainAutoRenewal extends AbstractCommand
{
    public function handle(SubscriptionRepository $subscriptionRepository, Dispatcher $jobDispatcher): int
    {
        $subscriptions = $subscriptionRepository->getDomainSubscriptionsDueForAutoRenewalDisable();

        $this->line('Disabling autorenewal for ' . $subscriptions->count() . ' domain subscriptions');
        $bar = $this->output->createProgressBar(count($subscriptions));

        foreach ($subscriptions as $subscription) {
            if ($subscription->domainDeployment === null) {
                $subscription->technical_status = TechnicalStatus::CANCELED->value;
                $subscription->save();

                $this->line(sprintf(
                    'No domain deployment found for subscription %s (%s), skipping.',
                    $subscription->domain ?? '',
                    $subscription->uuid,
                ));

                $bar->advance();
                continue;
            }

            $this->line(sprintf(
                'Dispatching disable domain auto-renewal for subscription %s (%s)',
                $subscription->domain ?? '',
                $subscription->uuid,
            ));

            $jobDispatcher->dispatch(new DisableDomainAutoRenewalJob($subscription->domainDeployment));

            $bar->advance();
        }

        $bar->finish();

        $this->line('Finished with disabling autorenewal for the subscriptions');

        return self::SUCCESS;
    }
}
