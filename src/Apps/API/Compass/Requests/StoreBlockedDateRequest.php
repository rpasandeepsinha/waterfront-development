<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlockedDateRequest extends FormRequest
{
    /** @return array<string> */
    public function rules(): array
    {
        return [
            'date' => 'date|after:today',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
