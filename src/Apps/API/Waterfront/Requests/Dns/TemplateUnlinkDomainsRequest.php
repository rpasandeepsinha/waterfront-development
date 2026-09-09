<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array<mixed> $domains
 */
class TemplateUnlinkDomainsRequest extends FormRequest
{
    /**
     * @return array<string,string>
     */
    public function rules(): array
    {
        return [
            'domains' => 'required|array',
            'domains.*.domain' => 'required|exists:subscriptions,domain',
        ];
    }
}
