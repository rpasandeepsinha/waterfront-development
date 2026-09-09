<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Redirect;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|RedirectFromUrlRule|Enum>|string>
     */
    public function rules(RedirectFromUrlRule $redirectFromUrlRule): array
    {
        return [
            'old' => 'required|array',
            'old.source' => [
                'required',
                $redirectFromUrlRule,
            ],
            'old.target' => [
                'required',
                'url',
            ],
            'old.type' => [
                'required',
                'string',
                new Enum(RedirectType::class),
            ],
            'new' => 'required|array',
            'new.source' => [
                'required',
                $redirectFromUrlRule,
            ],
            'new.target' => [
                'required',
                'url',
            ],
            'new.type' => [
                'required',
                'string',
                new Enum(RedirectType::class),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $default = RedirectType::PERMANENT->value;

        /** @var array<string, mixed> $old */
        $old = (array) $this->input('old', []);

        /** @var array<string, mixed> $new */
        $new = (array) $this->input('new', []);

        $old['type'] ??= $default;
        $new['type'] ??= $default;

        $this->merge([
            'old' => $old,
            'new' => $new,
        ]);
    }
}
