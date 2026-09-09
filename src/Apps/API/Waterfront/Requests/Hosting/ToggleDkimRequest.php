<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Hosting;

use Illuminate\Foundation\Http\FormRequest;

class ToggleDkimRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'domain' => 'required|string',
            'enabled' => 'required|boolean',
        ];
    }
}
