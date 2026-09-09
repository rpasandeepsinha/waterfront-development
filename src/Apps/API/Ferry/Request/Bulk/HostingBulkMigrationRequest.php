<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Bulk;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;

class HostingBulkMigrationRequest extends BaseRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        $bulkRules = [];

        $singleRules = array_merge(
            MigrationValidationLibrary::getHostingBaseRules(),
            [
                '*.reference_subscription_id' => 'required|string',
                '*.hostname' => 'required|string',
            ],
        );

        foreach ($singleRules as $key => $singleRule) {
            $bulkRules['*.subscriptions.' . $key] =  $singleRule;
        }

        return array_merge($bulkRules, [
            '*' => 'required|array',
            '*.waterfront_customer_id' => 'required|int',
            '*.subscriptions' => 'array',
        ]);
    }
}
