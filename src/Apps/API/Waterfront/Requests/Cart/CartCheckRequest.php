<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class CartCheckRequest extends FormRequest
{
    /**
     * @return array<string, array<int, Exists|string|In>>
     */
    public function rules(): array
    {
        return [
                'paymentMethod' => ['sometimes', 'required', 'string'],

                'vouchers' => ['sometimes', 'array'],
                'vouchers.*' => ['sometimes', 'required', 'string'],

                'items' => ['array'],
                'items.*.itemUuid'         => ['required', 'uuid'],
                'items.*.parentItemUuid'   => ['sometimes', 'nullable', 'uuid'],
                'items.*.parentSubscriptionUuid'  => ['sometimes', 'required',
                    Rule::exists(sprintf('%s', Subscription::class), 'uuid')->where('administrative_status', AdministrativeStatus::ACTIVE->value),
                ],
                'items.*.productSlug'      => ['required', Rule::exists(Product::class, 'slug')],
                'items.*.contractPeriod'  => ['required', 'integer', 'min:1'],
                'items.*.billingPeriod'   => ['required', 'integer', 'min:1'],
                'items.*.priceType'        => ['required', Rule::in([ProductPriceType::PROLONGATION, ProductPriceType::REGISTRATION])],
                'items.*.quantity'         => ['required', 'integer', 'min:1'],
                'items.*.metadata'         => ['nullable', 'array'],
                'items.*.metadata.domain'  => ['sometimes', 'nullable', 'string'],
                'items.*.metadata.transferCode'  => ['sometimes', 'nullable', 'string'],
                'items.*.metadata.contactHandle'  => ['sometimes', 'nullable', 'int'],
                'items.*.metadata.microsoft365TenantName'  => ['sometimes', 'nullable', 'string'],
                'items.*.metadata.microsoft365TenantId'  => ['sometimes', 'nullable', 'string'],
                'items.*.metadata.experimentSlug'  => ['sometimes', 'required', Rule::exists(Experiment::class, 'slug'),
            ],
        ];
    }
}
