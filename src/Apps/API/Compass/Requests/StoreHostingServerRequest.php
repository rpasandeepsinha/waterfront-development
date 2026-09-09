<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Validation\Rule;

class StoreHostingServerRequest extends AbstractHostingServerRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'hostname' => [
                'required',
                'string',
                'max:255',
                Rule::unique('hosting_servers', 'hostname')->whereNull('deleted_at'),
            ],
        ]);
    }
}
