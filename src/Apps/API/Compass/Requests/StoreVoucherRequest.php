<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Infra\Translation\TranslatorInterface;

class StoreVoucherRequest extends FormRequest
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'displayName'                    => ['required', 'string', 'max:255'],
            'internalName'                   => ['required', 'string', 'max:255'],
            'description'                    => ['sometimes', 'nullable', 'string'],
            'code'                           => ['required', 'string', 'max:255', 'unique:vouchers,code'],
            'amount'                         => ['required', 'numeric', 'min:0'],
            'amountType'                     => ['sometimes', 'nullable', Rule::enum(VoucherAmountType::class)],
            'maxClaims'                      => ['sometimes', 'nullable', 'integer', 'min:1'],
            'billingPeriod'                  => ['sometimes', 'nullable', 'integer', 'min:1'],
            'contractPeriod'                 => ['sometimes', 'nullable', 'integer', 'min:1'],
            'expirationDate'                 => ['sometimes', 'nullable', 'date', 'after:today'],
            'applyWithDiscount'              => ['required', 'boolean'],
            'allowMultipleClaimsSameCustomer' => ['required', 'boolean'],
            'productSlug'                    => [
                'sometimes',
                'nullable',
                'string',
                'exists:products,slug',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $groupSlug = $this->input('productGroupSlug');

                    if (! is_string($value) || ! is_string($groupSlug)) {
                        return;
                    }

                    $group = ProductGroupType::tryFrom($groupSlug);

                    if ($group === null || ! $this->productRepository->slugExistsForGroup($value, $group)) {
                        $fail($this->translator->translate('validation.product_not_in_group'));
                    }
                },
            ],
            'productGroupSlug'               => ['sometimes', 'nullable', 'string', Rule::enum(ProductGroupType::class)],
        ];
    }
}
