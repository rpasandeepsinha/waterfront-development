<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;

/** @property ProductIntroductionDiscount $resource */
class NovaIntroductionPricingResource extends Resource
{
    public static string $model = ProductIntroductionDiscount::class;

    public static function getTranslationKey(): string
    {
        return 'introduction-pricing';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.introduction-pricing');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('introduction-pricing.attributes.product'),
                'product',
                NovaProductResource::class,
            )->searchable(),
            Number::make(
                self::translate('introduction-pricing.attributes.period'),
                'contract_period',
            )->creationRules([
                'required',
                Rule::unique('product_introduction_discounts')->where(
                    fn ($query) => $query
                        ->where('product_id', $request->product)
                        ->where('contract_period', $request->contract_period)
                        ->whereNull('deleted_at'),
                ),
            ]),
            Number::make(
                self::translate('introduction-pricing.attributes.amount'),
                'max_uses_per_customer',
            )->nullable(),
            Number::make(
                self::translate('introduction-pricing.attributes.first-months-discount-period'),
                'first_months_discount_period',
            )->nullable(),
        ];
    }
}
