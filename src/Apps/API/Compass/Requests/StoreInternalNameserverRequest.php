<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string $nameserver_hostname
 */
class StoreInternalNameserverRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'nameserver_hostname' => [
                'required',
                'string',
                Rule::unique('migrated_dns_internal_nameservers', 'nameserver_hostname'),
            ],
        ];
    }
}
