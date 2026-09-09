<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Hosting\Enums\HostingRetryType;

/**
 * @property string $hosting_type
 * @property ?int   $server_id
 */
class RetryHostingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hosting_type' => ['required', Rule::enum(HostingRetryType::class)],
            'server_id' => ['sometimes', 'nullable', 'integer', 'exists:hosting_servers,id'],
        ];
    }
}
