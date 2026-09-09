<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Redirect;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Domains\Rules\RedirectFromUrlRule;

/**
 * @property string $source
 */
class DestroyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|RedirectFromUrlRule>|string>
     */
    public function rules(RedirectFromUrlRule $redirectFromUrlRule): array
    {
        return [
            'source' => [
                'required',
                $redirectFromUrlRule,
            ],
        ];
    }
}
