<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Monarobase\CountryList\CountryListFacade as Countries;
use Propaganistas\LaravelPhone\Rules\Phone;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

class StoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        $countries = Countries::getList('nl', 'php');

        /** @var array<mixed> $countryCodeArray */
        $countryCodeArray = array_keys($countries);

        return [
            'email' => [
                'required',
                'email:filter',
                'between:3,191',
            ],
            'first_name' => ['required', 'max:255', new FilterSpecialChars()],
            'last_name' => ['required', 'max:255', new FilterSpecialChars()],
            'phone_number' => ['required', new Phone()],
            'organization' => ['nullable', 'max:255', new FilterSpecialChars()],
            'street_name' => ['required', 'max:60', new FilterSpecialChars(',.'), 'regex:/\D/'],
            'street_number' => ['required', 'integer', 'max_digits:5'],
            'zip_code' => ['required', 'string', 'postal_code_with:country_code'],
            'city' => ['required', 'max:85', new FilterSpecialChars()],
            'country_code' => ['required', Rule::in($countryCodeArray)],
        ];
    }
}
