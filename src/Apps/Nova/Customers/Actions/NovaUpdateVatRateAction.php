<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use SandwaveIo\Vat\Exceptions\VatFetchFailedException;
use Waterfront\Domain\Customers\Jobs\UpdateCustomerVatRate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaUpdateVatRateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.update_vat_rate');
    }

    /**
     * @param Collection<int, Customer> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $models->each(function (Customer $customer) {
            try {
                $this->jobDispatcher->dispatchSync(new UpdateCustomerVatRate($customer));
            } catch (VatFetchFailedException $e) {
                return self::danger($e->getMessage());
            }
        });

        return Action::message($this->translator->translate('nova-action.success.update_vat_rate_successfully'));
    }
}
