<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $searchterm
 */
class SearchTermRequest extends FormRequest
{
    /**
     * @return array<string>
     */
    public function rules(): array
    {
        return [
            'searchterm' => 'required|string|max:64',
        ];
    }
}
