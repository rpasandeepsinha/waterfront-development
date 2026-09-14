<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Validation\Rule;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

class CreateProductRequest extends AbstractProductRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => [
                'required',
                'string',
                'unique:products,slug',
                'lowercase',
                new FilterSpecialChars(excludechars: '_'),
            ],
            'name' => ['required', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'specifications.*.name' => [
                'sometimes',
                'required',
                Rule::in(array_column(ProductSpecName::cases(), 'value')),
            ],
            'specifications.*.value' => ['sometimes', 'required_if:specifications.*.key,string', 'string'],
            'allowedChange.*.type' => [
                'sometimes',
                'required',
                Rule::in(array_column(ProductChangeType::cases(), 'value')),
            ],
            'allowedChange.*.toProductId' => ['sometimes', 'integer', 'exists:products,id'],
        ]);
    }
}
