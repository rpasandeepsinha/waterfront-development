<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductDiscountPricesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prices' => ['present', 'array'],
            'prices.*.product_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'prices.*.billing_period' => ['required', 'integer', 'min:1'],
            'prices.*.contract_period' => ['required', 'integer', 'min:1'],
            'prices.*.registration_staffel_price' => ['required', 'integer', 'min:0'],
            'prices.*.prolongation_staffel_price' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateNoDuplicatePriceRows($validator);
        });
    }

    private function validateNoDuplicatePriceRows(Validator $validator): void
    {
        $prices = $this->input('prices', []);

        if (! is_array($prices)) {
            return;
        }

        $seen = [];

        foreach ($prices as $index => $entry) {
            if (
                ! is_array($entry)
                || ! array_key_exists('product_id', $entry)
                || ! array_key_exists('billing_period', $entry)
                || ! array_key_exists('contract_period', $entry)
                || ! is_int($entry['product_id'])
                || ! is_int($entry['billing_period'])
                || ! is_int($entry['contract_period'])
            ) {
                continue;
            }

            $key = $entry['product_id'] . '_' . $entry['billing_period'] . '_' . $entry['contract_period'];

            if (array_key_exists($key, $seen)) {
                $validator->errors()->add(
                    "prices.{$index}.product_id",
                    'The combination of product_id, billing_period and contract_period must be unique within the request.',
                );
            }

            $seen[$key] = true;
        }
    }
}
