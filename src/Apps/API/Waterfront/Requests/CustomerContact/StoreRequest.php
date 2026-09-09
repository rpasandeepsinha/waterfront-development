<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CustomerContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Waterfront\Domain\Customers\Enums\CustomerContactType;

/**
 * @property string $email
 * @property string $type
 */
class StoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, In|string>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|not_regex:[&]',
            'type' => [
                'required',
                Rule::in(CustomerContactType::cases()),
            ],
        ];
    }
}
