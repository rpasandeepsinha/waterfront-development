<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;

class HostingMigrationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            MigrationValidationLibrary::getHostingBaseRules(),
            [
                '*.reference_subscription_id' => 'required|string|distinct|exists:migrated_subscriptions,reference_subscription_id',
            ],
        );
    }
}
