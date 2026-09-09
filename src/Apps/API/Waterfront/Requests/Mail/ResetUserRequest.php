<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Mail;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class ResetUserRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                'string',
                'min:6',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $translator = $this->container->make(TranslatorInterface::class);

        return [
            'password.min' => $translator->translate('mail-providers.password-min'),
            'password.regex' => $translator->translate('mail-providers.password-regex'),
        ];
    }
}
