<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Redirect;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

class StoreRequest extends FormRequest
{
    /**
     *
     * @return array<string, array<int, string|RedirectFromUrlRule|Enum>>
     */
    public function rules(RedirectFromUrlRule $redirectFromUrlRule): array
    {
        return [
            'source' => [
                'required',
                $redirectFromUrlRule,
            ],
            'target' => [
                'required',
                'url',
            ],
            'type' => [
                'required',
                'string',
                new Enum(RedirectType::class),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type', RedirectType::PERMANENT->value),
        ]);
    }
}
