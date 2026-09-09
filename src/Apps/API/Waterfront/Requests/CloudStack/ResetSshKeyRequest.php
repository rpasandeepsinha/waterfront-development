<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CloudStack;

use Illuminate\Foundation\Http\FormRequest;

class ResetSshKeyRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'ssh_uuid' => 'required|uuid|exists:cloudstack_vm_ssh_keys,uuid',
        ];
    }
}
