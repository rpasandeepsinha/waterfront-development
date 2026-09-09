<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Bulk;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class DomainBulkMigrationRequest extends BaseRequest
{
    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            '*' => 'required|array',
            '*.waterfront_customer_id' => 'required|int',
            '*.subscriptions' => 'array',
            '*.subscriptions.*.reference_subscription_id' => 'sometimes|string',
            '*.subscriptions.*.domain_data.reference_dns_template_id' => 'sometimes|nullable',
        ];
    }
}
