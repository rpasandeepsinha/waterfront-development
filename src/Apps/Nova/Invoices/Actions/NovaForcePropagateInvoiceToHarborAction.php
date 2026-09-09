<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\InvoiceToHarborDispatcher;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaForcePropagateInvoiceToHarborAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly InvoiceToHarborDispatcher $dispatcher,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.harbor_force_propagate');
    }

    /**
     * @return array<int,NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make(
                $this->translator->translate('nova-action.harbor_dispatch_instantly_create_invoice.direct'),
                'create_invoice_instantly'
            )->help(
                $this->translator->translate('nova-action.harbor_dispatch_instantly_create_invoice')
            ),
        ];
    }

    /**
     * @param Collection<int, Invoice> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $models->ensure(Invoice::class);

        if ($models->filter(
            fn (Invoice $invoice) => $invoice->sent_to_harbor_at !== null
        )->isNotEmpty()) {
            return self::danger($this->translator->translate('nova-action.error.invoice_already_sent_to_harbor'));
        }

        $createInvoiceInstantly = boolval($fields->get('create_invoice_instantly'));
        $this->dispatcher->dispatch($models, $createInvoiceInstantly);

        return self::message($this->translator->translate('nova-action.success.invoice_propagated_to_harbor'));
    }
}
