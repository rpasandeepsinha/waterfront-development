<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainName;

use Illuminate\Foundation\Http\FormRequest;

class DomainNameCoupleRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid', 'exists:subscriptions,uuid'],
        ];
    }
}
