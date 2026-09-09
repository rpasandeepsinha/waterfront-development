<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;

/**
 * @property string  $hostname
 * @property string  $ipv4
 * @property ?string $ipv6
 * @property string  $originalBusinessUnit
 */
class StoreLegacyRedirectingServerRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        $table = new LegacyRedirectingServer()->getTable();

        return [
            'hostname' => ['required', 'string', 'max:255', Rule::unique($table, 'hostname')],
            'ipv4' => ['required', 'ipv4', Rule::unique($table, 'ipv4')],
            'ipv6' => ['nullable', 'ipv6', Rule::unique($table, 'ipv6')],
            'originalBusinessUnit' => ['required', 'string', 'max:255'],
        ];
    }
}
