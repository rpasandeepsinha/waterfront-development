<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Microsoft365;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $domain
 * @property string $privateKey
 */
class UpdatePrimaryDomainRequest extends FormRequest
{
    /**
     * @return array<string,array<string>>
     */
    public function rules(): array
    {
        return [
            'subscription' => [
                'required',
                'uuid',
            ],
        ];
    }
}
