<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Country;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use libphonenumber\NumberParseException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSetPhoneNumberAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.set_phone_number');
    }

    /**
     * @param Collection<int, Customer> $models
     *
     * @throws NumberParseException
     */
    public function handle(ActionFields $fields, Collection $models): void
    {
        $models->each(function (Customer $customer) use ($fields): void {
            $customer->setPhoneNumberAttribute($fields->phone_number);
            $customer->save();
        });
    }

    /**
     * @return array<int, Text|Country>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make($this->translator->translate('customer.attributes.phone'), 'phone_number'),
        ];
    }
}
