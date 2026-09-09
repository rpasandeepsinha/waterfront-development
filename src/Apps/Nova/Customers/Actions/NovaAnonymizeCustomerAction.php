<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Admin\Actions\AnonymizeCustomerAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaAnonymizeCustomerAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.anonymize_customer');
    }

    /**
     * @param Collection<int, Customer> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if (! $fields->confirm_check) {
            return self::danger($this->translator->translate('nova-action.anonymize-customer.error.did-not-confirm'));
        }

        $customer = $models->first();

        if (! $customer instanceof Customer) {
            return self::danger($this->translator->translate('nova-action.error.model-not-customer-model'));
        }

        if ($customer->anonymized_at !== null) {
            return self::danger($this->translator->translate('nova-action.anonymize-customer.error.already-anonymized'));
        }

        try {
            /** @var AnonymizeCustomerAction $anonymizeCustomerAction */
            $anonymizeCustomerAction = resolve(AnonymizeCustomerAction::class);
            $anonymizeCustomerAction->execute($customer);
        } catch (AnonymizeCustomerException $exception) {
            return self::danger($exception->getMessage());
        }

        return self::message($this->translator->translate('nova-action.anonymize_customer.successful'));
    }

    /**
     * @return array<int, NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make('Confirm', 'confirm_check')
                ->help($this->translator->translate('nova-action.anonymize-customer.confirmtext')),
        ];
    }
}
