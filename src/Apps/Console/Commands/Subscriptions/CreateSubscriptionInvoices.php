<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Subscriptions;

use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Invoices\Services\InvoiceJobDispatcher;

#[AsCommand(name: 'subscriptions:create-invoices')]
#[Description('Command to create invoices for eligible subscriptions')]
class CreateSubscriptionInvoices extends AbstractCommand
{
    public function handle(InvoiceJobDispatcher $jobDispatcher): int
    {
        $jobDispatcher->dispatchJobs();

        return self::SUCCESS;
    }
}
