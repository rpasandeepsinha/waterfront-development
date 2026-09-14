<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CloudStack;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class CreateSshKeyRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'key_name' => ['required', 'string', 'regex:/^[0-9A-Za-z_ -]{2,50}$/'],
            'ssh_key' => [
                'required',
                'string',
                'regex:/^(?:(?:ssh-(?:rsa|dss)|ecdsa-sha2-nistp(?:256|384|521)|ssh-ed25519))\s+([A-Za-z0-9+\/]+={0,3})(?:\s+.*)?$/',
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
            'key_name.required' => $translator->translate('ssh-key.name-required'),
            'key_name.string' => $translator->translate('ssh-key.name-string'),
            'key_name.regex' => $translator->translate('ssh-key.name-regex'),
            'ssh_key.required' => $translator->translate('ssh-key.pubkey-required'),
            'ssh_key.string' => $translator->translate('ssh-key.pubkey-string'),
            'ssh_key.regex' => $translator->translate('ssh-key.invalid-pubkey-format'),
        ];
    }
}
