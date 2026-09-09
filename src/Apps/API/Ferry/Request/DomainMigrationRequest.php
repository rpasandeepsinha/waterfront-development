<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class DomainMigrationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            MigrationValidationLibrary::getDomainBaseRules(),
            [
                '*.reference_subscription_id' => [
                    'sometimes',
                    'string',
                    'exists:migrated_subscriptions,reference_subscription_id',
                ],
                '*.domain_data.reference_dns_template_id' => [
                    'sometimes',
                    'nullable',
                    'exists:migrated_dns_templates,reference_template_id',
                ],
                '*.reference_domain_provider_business_unit_slug' => [
                    'sometimes',
                    'string',
                    'exists:domain_provider_business_unit,slug',
                ],
            ],
        );
    }
}
