<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Hosting;

use Illuminate\Foundation\Http\FormRequest;

class GenerateHostingSsoUrlRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
