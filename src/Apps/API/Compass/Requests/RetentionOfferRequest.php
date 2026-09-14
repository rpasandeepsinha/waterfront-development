<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\ExecutionDate;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Webmozart\Assert\Assert;

class RetentionOfferRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'customerType' => ['required', Rule::enum(CustomerType::class)],
            'puzzelTicketId' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.subscriptionUuid' => ['required', 'distinct:strict', 'uuid', 'exists:subscriptions,uuid'],
            'items.*.selectedAction' => ['required', Rule::enum(SelectedAction::class)],
            'items.*.executionDate' => ['required', Rule::enum(ExecutionDate::class)],
            'items.*.contractPeriod' => ['sometimes', 'nullable', 'int', 'min:1'],
            'items.*.billingPeriod' => ['sometimes', 'nullable', 'int', 'min:1'],
            'items.*.targetProductUuid' => ['sometimes', 'nullable', 'uuid', 'exists:products,uuid'],
            'items.*.cancelReason' => ['sometimes', 'nullable', Rule::enum(SubscriptionCancelReason::class)],
            'items.*.cancelReasonOther' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function customerType(): CustomerType
    {
        $customerType = $this->enum('customerType', CustomerType::class);
        Assert::notNull($customerType);

        return $customerType;
    }
}
