<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Requests\DomainName;

use Illuminate\Foundation\Http\FormRequest;

class RequestPremiumPriceRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string'],
        ];
    }
}
