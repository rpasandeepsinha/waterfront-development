<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainContact;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'domains' => 'required|array',
            'domains.*.domain' => 'required',
            'domains.*.type' => 'required',
            Rule::in(['owner', 'admin', 'tech']),
        ];
    }
}
