<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Bulk;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class SubscriptionBulkCreateRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // To support bulk it is important to keep performance in mind. Doing validation here is too much load
        // at this point is too heavy. We chose to lean on the validation pipeline
        // for the customer contextual validation.
        return [
            '*' => 'required|array',
            '*.reference_customer_id' => 'required|string',
            '*.customer_id' => 'required|integer',
        ];
    }
}
