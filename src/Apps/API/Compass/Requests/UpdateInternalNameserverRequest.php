<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;

/**
 * @property string $nameserver_hostname
 */
class UpdateInternalNameserverRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $nameserver = $this->route('ferryInternalNameserver');

        return [
            'nameserver_hostname' => [
                'required',
                'string',
                Rule::unique('migrated_dns_internal_nameservers', 'nameserver_hostname')
                    ->ignore($nameserver instanceof FerryInternalNameserver ? $nameserver->id : null),
            ],
        ];
    }
}
