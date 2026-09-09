<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Carbon\CarbonImmutable;
use Illuminate\Log\Logger;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class InvoiceablesReporter
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger $logger,
    ) {
    }

    public function report(): void
    {
        $this->logger->debug('Preparing to send daily invoiceables reporting');

        $invoices = $this->invoiceRepository->countNotSentToHarbor();
        $subscriptions = $this->subscriptionRepository->countDueForInvoicing(CarbonImmutable::now());
        $migrated = $this->invoiceRepository->countMigratedNotSentToHarbor();

        $this->logger->info('Daily invoiceables reporting', [
            LoggingContextKeys::REPORTING_DATA => [
                'invoices' => $invoices,
                'subscriptions' => $subscriptions,
                'migrated' => $migrated,
            ],
        ]);
    }
}
