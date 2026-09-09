<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteBlockedDateRequest extends FormRequest
{
    /** @return array<string> */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:puzzel_blocked_dates,id',
        ];
    }
}
