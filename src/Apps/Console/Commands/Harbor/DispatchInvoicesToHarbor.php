<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Harbor;

use Illuminate\Console\Attributes\Description;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Apps\Console\Commands\AbstractCommand;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\InvoiceToHarborDispatcher;

#[AsCommand(name: 'harbor:dispatch-invoices-to-harbor')]
#[Description('Command to dispatch invoices to harbor')]
class DispatchInvoicesToHarbor extends AbstractCommand
{
    public function handle(InvoiceRepository $invoiceRepository, InvoiceToHarborDispatcher $dispatcher): int
    {
        $invoicesNotSentToHarbor = $invoiceRepository->getNotSentToHarbor();
        $this->line('Attempt to dispatch ' . $invoicesNotSentToHarbor->count() . ' invoices');

        $dispatcher->dispatch($invoicesNotSentToHarbor);

        return self::SUCCESS;
    }
}
