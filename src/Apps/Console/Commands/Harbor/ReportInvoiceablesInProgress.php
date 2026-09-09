<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Harbor;

use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Invoices\Services\InvoiceablesReporter;

#[AsCommand(name: 'harbor:report-invoicables-in-progress')]
#[Description('Command for sending a report about invoiceables which are in progress')]
class ReportInvoiceablesInProgress extends AbstractCommand
{
    public function handle(
        InvoiceablesReporter $progressReporter
    ): int {
        $progressReporter->report();
        return self::SUCCESS;
    }
}
