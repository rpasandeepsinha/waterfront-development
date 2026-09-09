<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Infra\Common\DateTimeFormat;

/**
 * @property list<string> $subscription_uuids
 */
class CheckCancelSubscriptionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_uuids' => ['required', 'array', 'min:1'],
            'subscription_uuids.*' => ['required', 'distinct:strict', 'uuid', 'exists:subscriptions,uuid'],
            'reason' => ['nullable', Rule::enum(SubscriptionCancelReason::class)],
            'reason_other' => ['nullable', 'string', 'max:255'],
            'type' => [
                'nullable',
                Rule::enum(SubscriptionCancelType::class)->only([
                    SubscriptionCancelType::CANCEL_END_DATE,
                    SubscriptionCancelType::CANCEL_OTHER,
                ]),
            ],
            'type_other_date' => [
                'nullable',
                'required_if:type,' . SubscriptionCancelType::CANCEL_OTHER->value,
                'date_format:' . DateTimeFormat::DATE,
            ],
            'credit' => ['nullable', 'boolean'],
        ];
    }

    public function cancelType(): ?SubscriptionCancelType
    {
        return $this->enum('type', SubscriptionCancelType::class);
    }

    public function cancelReason(): ?SubscriptionCancelReason
    {
        return $this->enum('reason', SubscriptionCancelReason::class);
    }
}
