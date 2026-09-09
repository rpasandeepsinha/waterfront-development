<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Admin\Actions\MarkCustomerAsAbuseAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMarkCustomerAsAbuseAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MarkCustomerAsAbuseAction $markCustomerAsAbuseAction,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.mark_customer_as_abuse');
    }

    /**
     * @param Collection<int, Customer> $models
     *
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if (! $fields->confirm_check) {
            return self::danger($this->translator->translate('nova-action.mark_customer_as_abuse.error.did-not-confirm'));
        }

        $customer = $models->first();

        if (! $customer instanceof Customer) {
            return self::danger($this->translator->translate('nova-action.error.model-not-customer-model'));
        }

        $this->markCustomerAsAbuseAction->execute($customer);

        return self::message($this->translator->translate('nova-action.mark_customer_as_abuse.successful'));
    }

    /**
     * @return array<int, NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make('Confirm', 'confirm_check')
                ->help($this->translator->translate('nova-action.mark_customer_as_abuse.confirmtext')),
        ];
    }
}
