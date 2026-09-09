<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Validation\Rule;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

class UpdateProductRequest extends AbstractProductRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return array_merge(parent::rules(), [
            'slug'                                  => ['required', 'string', Rule::unique('products', 'slug')->ignore($product->id), new FilterSpecialChars(excludechars: '(- _.)')],
            'name'                                  => ['required', 'string', 'max:255'],
            'description'                           => ['sometimes', 'nullable', 'string', 'max:255'],
            'shopConfig.requirementsTranslationKey' => ['sometimes', 'nullable', 'string', 'exists:translation_keys,key', 'max:255'],
            'specifications.*.name'                 => ['sometimes', 'required', 'max:255', Rule::in(array_column(ProductSpecName::cases(), 'value'))],
            'specifications.*.value'                => ['sometimes', 'max:255', 'required_if:specifications.*.key,string', 'string'],
            'allowedChange.*.type'                  => ['sometimes', 'required', Rule::in(array_column(ProductChangeType::cases(), 'value'))],
            'allowedChange.*.toProductId'           => ['sometimes', 'integer', 'exists:products,id'],
            'promotions.*.uuid'                     => ['sometimes', 'required', 'uuid'],
            'addons.*.product_id'                   => ['required_with:addons.*', 'integer', 'distinct', 'exists:products,id', Rule::notIn([$product->id])],
        ]);
    }
}
