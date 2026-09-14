<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetrySslDeploymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'csr' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^-----BEGIN CERTIFICATE REQUEST-----(.|\s)+-----END CERTIFICATE REQUEST-----$/',
            ],
        ];
    }
}
