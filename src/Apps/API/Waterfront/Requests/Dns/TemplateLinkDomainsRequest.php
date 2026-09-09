<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array<mixed> $domains
 */
class TemplateLinkDomainsRequest extends FormRequest
{
    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'domains' => 'required|array',
            'domains.*.domain' => 'required|exists:subscriptions,domain',
        ];
    }
}
