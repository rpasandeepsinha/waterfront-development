<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Customers\Enums\CustomerContactType;

/**
 * @property string      $first_name
 * @property string      $last_name
 * @property string|null $company
 * @property string      $email
 * @property string      $type
 */
class UpdateContactRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'company' => ['nullable', 'string'],
            'email' => ['required', 'email:rfc,dns'],
            'type' => ['required', Rule::enum(CustomerContactType::class)],
        ];
    }
}
