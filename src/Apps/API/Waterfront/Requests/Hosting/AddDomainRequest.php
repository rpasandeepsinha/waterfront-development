<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Hosting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $domain_subscription_uuid
 * @property string $hosting_subscription_uuid
 */
class AddDomainRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'domain_subscription_uuid' => 'required',
            'hosting_subscription_uuid' => 'required',
        ];
    }
}
