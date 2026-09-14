<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Invoices\Jobs\CreateSubscriptionInvoicesForCustomer;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class InvoiceJobDispatcher
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Dispatcher $bus,
        private readonly ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatchJobs(): void
    {
        $renewalDays = $this->configuration->getAsInteger('constants.invoice-ahead-days');
        $billingDate = CarbonImmutable::today()->addDays($renewalDays);

        $customers = $this->subscriptionRepository->getAllCustomersEligibleForInvoicing($billingDate);

        foreach ($customers as $customer) {
            $this->logger->info(sprintf(
                'Dispatching invoice renewal job for customer %d',
                $customer->id,
            ));

            $command = new CreateSubscriptionInvoicesForCustomer(
                $customer,
                $billingDate,
            );
            $command->onQueue('subscriptions');

            $this->bus->dispatch($command);
        }
    }
}
