<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $hubspot_template_id
 * @property string $slug
 */
class CreateTemplateRequest extends FormRequest
{
    /** @return array<string> */
    public function rules(): array
    {
        return [
            'hubspot_template_id' => 'required|string|max:64',
            'slug' => 'required|string|max:64',
        ];
    }
}
