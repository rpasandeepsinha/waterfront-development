<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Label;

use Illuminate\Foundation\Http\FormRequest;

class DeleteLabelRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'labels' => 'required|array',
            'labels.*' => 'required|string|distinct|exists:labels,value',
        ];
    }
}
