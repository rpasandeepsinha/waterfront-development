<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string  $identifier
 * @property ?string $ipaddress
 * @property ?string $domain
 */
class FetchServerUserRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'ipaddress' => ['nullable', 'ip'],
            'domain' => ['nullable', 'string', 'max:255'],
        ];
    }
}
