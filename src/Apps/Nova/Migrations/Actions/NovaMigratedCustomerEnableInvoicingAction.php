<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Ferry\Actions\Customers\EnableInvoicingForCustomerAction;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMigratedCustomerEnableInvoicingAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EnableInvoicingForCustomerAction $enableInvoicingForCustomerAction,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.enable-invoicing');
    }

    /**
     * @param Collection<int, MigratedCustomer> $migratedCustomers
     */
    public function handle(ActionFields $fields, Collection $migratedCustomers): ActionResponse|static
    {
        $migratedCustomer = $migratedCustomers->firstOrFail();
        $migratedCustomer->refresh();

        if ($migratedCustomer->enable_invoicing) {
            return Action::message($this->translator->translate('nova-action.enable-invoicing.already-enabled'));
        }

        $customer = $migratedCustomer->customers->firstOrFail();

        if (! $migratedCustomer->administrative_successful) {
            // We're assuming that if you want to enable invoicing it is also administratively successful
            $migratedCustomer->administrative_successful = true;
            $migratedCustomer->save();
        }

        $this->enableInvoicingForCustomerAction->execute($customer);

        return Action::message($this->translator->translate('nova-action.enable-invoicing.success'));
    }
}
