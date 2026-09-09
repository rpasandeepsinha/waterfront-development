<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Label;

use Illuminate\Foundation\Http\FormRequest;

class DetachSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'subscription_ids' => 'required|array',
            'subscription_ids.*' => 'required|integer|distinct|exists:subscriptions,id',
        ];
    }
}
