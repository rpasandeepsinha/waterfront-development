<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Domain\Translations\Enums\TranslationSource;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Webmozart\Assert\Assert;

abstract class AbstractProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'groupSlug'                                    => ['required', 'string', Rule::in(array_column(ProductGroupType::cases(), 'value'))],
            'specifications'                               => ['sometimes', 'nullable', 'array'],
            'allowedChange'                                => ['sometimes', 'nullable', 'array'],
            'allowedChange.*.availabeForCustomer'          => ['sometimes', 'bool'],
            'shopConfig.weight'                            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'promotions'                                   => ['sometimes', 'nullable', 'array'],
            'promotions.*.platform'                        => ['sometimes', 'required', 'string', Rule::in(array_column(ProductPromotionPlatform::cases(), 'value'))],
            'promotions.*.placement_url'                   => ['sometimes', 'required', 'string'],
            'promotions.*.start_date'                      => ['required', 'date'],
            'promotions.*.end_date'                        => ['required', 'date', 'after:promotions.*.start_date'],
            'promotions.*.callToAction'                    => ['sometimes', 'required', 'array'],
            'promotions.*.callToAction.title'              => ['sometimes', 'required', 'string', Rule::exists(TranslationKey::class, 'key')->where('source', TranslationSource::COAST->value)],
            'promotions.*.callToAction.button_text'        => ['sometimes', 'required', 'string', Rule::exists(TranslationKey::class, 'key')->where('source', TranslationSource::COAST->value)],
            'promotions.*.callToAction.description'        => ['sometimes', 'required', 'string', Rule::exists(TranslationKey::class, 'key')->where('source', TranslationSource::COAST->value)],
            'promotions.*.callToAction.price_description'  => ['sometimes', 'required', 'string', Rule::exists(TranslationKey::class, 'key')->where('source', TranslationSource::COAST->value)],
            'promotions.*.callToAction.destination_url'    => ['sometimes', 'required', 'active_url', 'url:https'],
            'promotions.*.weight'                          => ['sometimes', 'required', 'min:0', 'int'],
            'prices'                                       => ['sometimes', 'nullable', 'array'],
            'prices.*.billing_period'                      => ['required_with:prices.*', 'integer'],
            'prices.*.contract_period'                     => ['required_with:prices.*', 'integer'],
            'prices.*.registration_price'                  => ['required_with:prices.*', 'integer', 'min:0'],
            'prices.*.additional_prices'                   => ['sometimes', 'nullable', 'array'],
            'prices.*.additional_prices.*.type'            => ['required', 'string', Rule::in(array_column([PriceComponentType::INTRODUCTION, PriceComponentType::PROLONGATION, PriceComponentType::PROMOTION], 'value'))],
            'prices.*.additional_prices.*.price'           => ['required', 'integer', 'min:0'],
            'addons'                                       => ['sometimes', 'nullable', 'array'],
            'addons.*.product_id'                          => ['required_with:addons.*', 'integer', 'distinct', 'exists:products,id'],
            'introduction_price_configuration'               => ['sometimes', 'nullable', 'array'],
            'introduction_price_configuration.*.contract_period' => ['required', 'integer', 'min:1'],
            'introduction_price_configuration.*.max_uses_per_customer' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'introduction_price_configuration.*.first_months_discount_period' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validatePeriods($validator);
            $this->validateNoDuplicateProductPricePeriods($validator);
            $this->validateIntroductionPriceConfiguration($validator);
        });
    }

    private function validateIntroductionPriceConfiguration(Validator $validator): void
    {
        $configurations = $this->input('introduction_price_configuration');

        if (! is_array($configurations)) {
            return;
        }

        $seenContractPeriods = [];

        foreach ($configurations as $index => $configuration) {
            if (! is_array($configuration) || ! is_numeric($configuration['contract_period'] ?? null)) {
                continue;
            }

            $contractPeriod = (int) $configuration['contract_period'];

            if (in_array($contractPeriod, $seenContractPeriods, true)) {
                $validator->errors()->add(
                    "introduction_price_configuration.{$index}.contract_period",
                    'The contract_period must be unique within the introduction price configuration.'
                );
            }

            $seenContractPeriods[] = $contractPeriod;

            $firstMonthsDiscountPeriod = $configuration['first_months_discount_period'] ?? null;

            if (! is_numeric($firstMonthsDiscountPeriod)) {
                continue;
            }

            if ((int) $firstMonthsDiscountPeriod > $contractPeriod) {
                $validator->errors()->add(
                    "introduction_price_configuration.{$index}.first_months_discount_period",
                    'The first_months_discount_period must not be greater than the contract_period of the same entry.'
                );
            }
        }
    }

    private function validatePeriods(Validator $validator): void
    {
        /** @var array<int, array<string, mixed>>|null $productPrices */
        $productPrices = $this->input('productPrices');

        if ($productPrices === null || $productPrices === []) {
            return;
        }

        foreach ($productPrices as $index => $entry) {
            $billingPeriod = $entry['billing_period'] ?? null;
            $contractPeriod = $entry['contract_period'] ?? null;
            Assert::integerish($billingPeriod);
            Assert::integerish($contractPeriod);

            if ($contractPeriod % $billingPeriod !== 0) {
                $validator->errors()->add(
                    "productPrices.{$index}.billing_period",
                    'The combination of billing_period and contract_period must result in a positive number'
                );
            }

            if ($contractPeriod >= $billingPeriod) {
                $validator->errors()->add(
                    "productPrices.{$index}.billing_period",
                    'The contract_period must be a larger number then billing_period'
                );
            }
        }
    }

    private function validateNoDuplicateProductPricePeriods(Validator $validator): void
    {
        /** @var array<int, array<string, mixed>>|null $productPrices */
        $productPrices = $this->input('productPrices');

        if ($productPrices === null || $productPrices === []) {
            return;
        }

        $seen = [];

        foreach ($productPrices as $index => $entry) {
            $billingPeriod = $entry['billing_period'] ?? '';
            $contractPeriod = $entry['contract_period'] ?? '';
            Assert::string($billingPeriod);
            Assert::string($contractPeriod);
            $key = $billingPeriod . '_' . $contractPeriod;

            if (array_key_exists($key, $seen)) {
                $validator->errors()->add(
                    "productPrices.{$index}.billing_period",
                    'The combination of billing_period and contract_period must be unique within the request.'
                );
            }

            $seen[$key] = true;
        }
    }
}
