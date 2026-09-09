<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Rules;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Monarobase\CountryList\CountryListFacade;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

class CustomerAddressRule extends FormRequest
{
    /** @return array<mixed> */
    public function getRules(): array
    {
        $countries = CountryListFacade::getList('nl', 'php');

        return [
            'street_name'   => ['required', 'max:60', new FilterSpecialChars(',.'), 'regex:/\D/'],
            'street_number' => ['required', 'integer', 'max_digits:5'],
            'street_number_addition' => ['nullable', 'string', 'min:1', 'max:10', new FilterSpecialChars()],
            'zip_code'      => ['required', 'string', 'postal_code_with:country_code'],
            'city'          => ['required', 'max:85', new FilterSpecialChars()],
            'country_code'  => ['required', Rule::in(array_keys($countries))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $translator = $this->container->make(TranslatorInterface::class);

        return [
            'street_name.regex' => $translator->translate('customer.streetnumber.digits'),
        ];
    }
}
