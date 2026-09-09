<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\NameServer;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Domains\Rules\DomainNameRule;

class UpdateRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(DomainNameRule $domainNameRule): array
    {
        return [
            'nameServers' => ['required', 'array', 'between:2,8'],
            'nameServers.*' => ['array'],
            'nameServers.*.name' => [
                'nullable',
                $domainNameRule,
                'distinct',
            ],
            'nameServers.0.name' => ['required'],
            'nameServers.1.name' => ['required'],
            'nameServers.*.ip' => ['nullable', 'ipv4'],
            'nameServers.*.ip6' => ['nullable', 'ipv6'],
        ];
    }
}
