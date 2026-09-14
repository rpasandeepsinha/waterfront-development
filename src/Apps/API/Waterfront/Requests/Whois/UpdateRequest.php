<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Whois;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Monarobase\CountryList\CountryListFacade as Countries;
use Propaganistas\LaravelPhone\Rules\Phone;

class UpdateRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        $countries = Countries::getList('nl', 'php');

        return [
            'organization' => ['nullable', 'max:255'],
            'email' => ['required', 'email'],
            'first_name' => ['required', 'max:255'],
            'last_name' => ['required', 'max:255'],
            'phone' => ['required', new Phone()],
            'address.street' => ['required', 'max:255'],
            'address.number' => ['required', 'max:255'],
            'address.zipcode' => ['required', 'max:255'],
            'address.city' => ['required', 'max:255'],
            'address.country' => ['required', Rule::in(array_keys($countries))],
        ];
    }
}
