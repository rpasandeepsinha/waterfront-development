<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string      $description
 * @property int|null    $maxClaims
 * @property string|null $expirationDate
 */
class UpdateVoucherRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'min:4'],
            'maxClaims' => ['sometimes', 'nullable', 'integer'],
            'expirationDate' => ['sometimes', 'nullable', 'string', 'date'],
        ];
    }
}
