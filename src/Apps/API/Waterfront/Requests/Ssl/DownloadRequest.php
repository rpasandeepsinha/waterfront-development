<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Ssl;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $uuid
 * @property string $type
 */
class DownloadRequest extends FormRequest
{
    /**
     * @return array<string,array<string>>
     */
    public function rules(): array
    {
        //TODO:: The download endpoint has no domain so we can't use the manageSsl policy
        return [
            'uuid' => ['required', 'string'],
            'type' => ['required', 'string'],
        ];
    }
}
