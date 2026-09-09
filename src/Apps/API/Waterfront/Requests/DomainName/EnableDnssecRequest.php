<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainName;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\StopOnFirstFailure;
use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Domains\Rules\DnssecKeyRule;

#[StopOnFirstFailure]
class EnableDnssecRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'flags' => ['integer', 'required_with:alg,pubKey'],
            'alg' => ['integer', 'required_with:flags,pubKey'],
            'pubKey' => ['string', 'required_with:flags,alg', new DnssecKeyRule()],
        ];
    }
}
