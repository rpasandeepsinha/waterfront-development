<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Hosting;

use Illuminate\Foundation\Http\FormRequest;

class FindDomainHostingCoupleRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'domain_subscription_uuid' => 'required|exists:domain_deployments,subscription_uuid',
        ];
    }
}
