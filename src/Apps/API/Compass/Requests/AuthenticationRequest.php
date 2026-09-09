<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string      $username
 * @property string      $password
 * @property string|null $two_factor_code
 */
class AuthenticationRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
            'two_factor_code' => 'sometimes|string',
        ];
    }
}
