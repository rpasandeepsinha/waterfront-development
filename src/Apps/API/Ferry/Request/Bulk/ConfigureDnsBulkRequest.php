<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Bulk;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureDnsBulkRequest extends FormRequest
{
    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        // To support bulk it is important to keep performance in mind. Doing validation here is too much load
        // at this point is too heavy. We chose to lean on the validation pipeline
        // for the customer contextual validation.
        return [
            '*.waterfront_customer_id' => 'required|integer',
        ];
    }
}
