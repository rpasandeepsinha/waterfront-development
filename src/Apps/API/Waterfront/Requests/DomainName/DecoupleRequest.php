<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainName;

use Illuminate\Foundation\Http\FormRequest;

class DecoupleRequest extends FormRequest
{
    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'uuid' => [
                'required',
                'uuid',
                'exists:subscriptions,uuid',
            ],
        ];
    }
}
