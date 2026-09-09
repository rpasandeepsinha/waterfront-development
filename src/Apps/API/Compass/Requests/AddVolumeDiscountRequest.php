<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $product_discount_id
 * @property int $period
 */
class AddVolumeDiscountRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'product_discount_id' => ['required', 'integer', 'min:1', 'exists:product_discounts,id'],
            'period' => ['required', 'integer', 'min:1'],
        ];
    }
}
