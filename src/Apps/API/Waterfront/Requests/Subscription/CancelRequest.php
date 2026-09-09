<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Rules\ProductShouldDowngradeCancelType;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

/**
 * @property array<int, array<string, mixed>> $subscriptions
 */
class CancelRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(SubscriptionService $subscriptionService): array
    {
        $uuids = $subscriptionService->getSubscriptionsQuery()->pluck('uuid');

        return [
            'subscriptions'                      => ['required', 'array'],
            'subscriptions.*'                    => ['array'],
            'subscriptions.*.uuid'               => ['required', Rule::in($uuids)],
            'subscriptions.*.cancel'             => ['required', 'boolean'],
            'subscriptions.*.cancel_type'        => ['nullable', Rule::enum(SubscriptionCancelType::class), new ProductShouldDowngradeCancelType()],
            'subscriptions.*.cancel_reason'      => ['required', Rule::enum(SubscriptionCancelReason::class)],
        ];
    }
}
