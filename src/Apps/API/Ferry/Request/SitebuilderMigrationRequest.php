<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class SitebuilderMigrationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            MigrationValidationLibrary::getSitebuilderBaseRules(),
            [
                '*.reference_subscription_id' => 'required|string|exists:migrated_subscriptions,reference_subscription_id',
            ],
        );
    }
}
