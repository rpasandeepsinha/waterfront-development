<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaCreateDirectDebitMandateAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.create_direct_debit_mandate');
    }

    /**
     * @param Collection<int, Customer> $customers
     */
    public function handle(ActionFields $fields, Collection $customers): ActionResponse|static
    {
        if ($customers->count() !== 1) {
            return self::danger($this->translator->translate('nova-action.error.multiple_models'));
        }

        $customer = $customers->first();

        assert($customer instanceof Customer);

        /** @var string $consumerName */
        $consumerName = $fields->get('consumer_name');
        /** @var string $consumerAccount */
        $consumerAccount = $fields->get('consumer_account');
        /** @var string $signatureDateValue */
        $signatureDateValue = $fields->get('signature_date');

        $this->dispatcher->dispatch(new RequestDirectDebitMandateJob($consumerName, $consumerAccount, null, $customer, new CarbonImmutable($signatureDateValue)));

        return self::message($this->translator->translate('nova-action.success.create_direct_debit_mandate.async'));
    }

    /**
     * @return array<int, Date|Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Date::make($this->translator->translate('nova-resource-labels.signature-date'), 'signature_date')
                ->rules('required')
                ->required(),

            Text::make('Consumer name', 'consumer_name')
                ->required()
                ->rules('required')
                ->help('Example: John Doe'),

            Text::make('Consumer IBAN', 'consumer_account')
                ->required()
                ->rules([
                    'required',
                    new IBAN(),
                ])
                ->help('Example: NL18RABO0123459876'),
        ];
    }
}
