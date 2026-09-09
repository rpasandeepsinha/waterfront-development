<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Mail;

use Illuminate\Foundation\Http\FormRequest;

class CreateForwardRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'source' => [
                'required',
                'string',
                'min:1',
                'not_regex:/@/', // no e-mail addresses, we only want the user part
            ],
            'destinations' => [
                'required',
                'array',
            ],
            'destinations.*' => [
                'required',
                'string',
                'email',
                'min:1',
            ],
        ];
    }
}
