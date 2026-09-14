<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Hosting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property bool $enableDns
 * @property bool $enableSsh
 * @property bool $enableSsl
 */
class UpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'enableDns' => ['boolean'],
            'enableSsh' => ['boolean'],
            'enableSsl' => ['boolean'],
        ];
    }
}
