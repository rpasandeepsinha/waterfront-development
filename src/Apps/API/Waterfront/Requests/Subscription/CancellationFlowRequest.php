<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property list<string> $subscriptionUuids
 */
class CancellationFlowRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscriptionUuids' => ['required', 'array'],
            'subscriptionUuids.*'  => ['bail', 'required', 'distinct:strict', 'uuid', 'exists:subscriptions,uuid'],
        ];
    }
}
