<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CloudStack;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReinstallVpsRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'os_product_uuid' => ['required', 'uuid', 'exists:products,uuid'],
            'ssh_key_uuid' => ['nullable', 'uuid', Rule::exists('cloudstack_vm_ssh_keys', 'uuid')],
        ];
    }
}
