<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Admin\Actions\GenerateActingForCustomerPanelUrlAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaLoginAsAction extends Action
{
    public $onlyOnDetail = true;

    public $showInline = true;

    public $withoutConfirmation = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly GenerateActingForCustomerPanelUrlAction $generateLoginAsCustomerAction,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.login_as');
    }

    /**
     * @param Collection<int, Customer> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $customer = $models->first();
        assert($customer instanceof Customer);

        return self::openInNewTab($this->generateLoginAsCustomerAction->execute($customer->customer_number));
    }
}
