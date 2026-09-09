<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CloudStack;

use Illuminate\Foundation\Http\FormRequest;

class CustomNameRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'custom_name' => 'required|string|min:1|max:50',
        ];
    }
}
