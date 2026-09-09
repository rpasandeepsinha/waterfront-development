<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class ValidationRequest extends BaseRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'reference' => 'required|string',
            'subscriptions' => 'required|array',
            'customer' => 'required|array',
        ];
    }
}
