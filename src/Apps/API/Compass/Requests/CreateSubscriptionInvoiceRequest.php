<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int   $gross_price
 * @property int   $net_price
 * @property ?bool $manually_add_admin_fees
 */
class CreateSubscriptionInvoiceRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'gross_price' => ['required', 'integer', 'min:0'],
            'net_price' => ['required', 'integer', 'min:0'],
            'manually_add_admin_fees' => ['sometimes', 'boolean'],
        ];
    }
}
