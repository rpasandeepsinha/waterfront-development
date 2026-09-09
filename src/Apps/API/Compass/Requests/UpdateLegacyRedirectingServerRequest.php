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
class UpdateLegacyRedirectingServerRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        $table = new LegacyRedirectingServer()->getTable();
        $legacyRedirectingServer = $this->route('legacy_redirecting_server');
        $ignoreId = $legacyRedirectingServer instanceof LegacyRedirectingServer ? $legacyRedirectingServer->id : null;

        return [
            'hostname' => ['required', 'string', 'max:255', Rule::unique($table, 'hostname')->ignore($ignoreId)],
            'ipv4' => ['required', 'ipv4', Rule::unique($table, 'ipv4')->ignore($ignoreId)],
            'ipv6' => ['nullable', 'ipv6', Rule::unique($table, 'ipv6')->ignore($ignoreId)],
            'originalBusinessUnit' => ['required', 'string', 'max:255'],
        ];
    }
}
