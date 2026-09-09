<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Requests\Microsoft365;

use Illuminate\Foundation\Http\FormRequest;

class TenantCheckRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'tenantName' => ['required', 'string', 'min:1', 'max:27', 'alpha_num'],
        ];
    }
}
