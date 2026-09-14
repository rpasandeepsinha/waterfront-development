<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Vouchers\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Orders\Resources\NovaOrderLineItemResource;
use Waterfront\Domain\Voucher\Models\VoucherClaim;

/** @property VoucherClaim $resource */
class NovaVoucherClaimsResource extends Resource
{
    public static string $model = VoucherClaim::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'voucher-claim';
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('nova-resource-labels.voucher-claims.fields.order-line-item'),
                'orderLineItem',
                NovaOrderLineItemResource::class,
            )->searchable(),

            BelongsTo::make(
                self::translate('nova-resource-labels.voucher-claims.fields.voucher'),
                'voucher',
                NovaVouchersResource::class,
            )->searchable(),

            Currency::make(
                self::translate('nova-resource-labels.voucher-claims.fields.amount-claimed'),
                'amount_claimed',
            )
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->required(),
        ];
    }

    public static function authorizedToCreate(Request $request): false
    {
        return false;
    }

    public function authorizedToDelete(Request $request): false
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): false
    {
        return false;
    }
}
