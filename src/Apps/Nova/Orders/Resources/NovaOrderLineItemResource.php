<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Orders\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Orders\Actions\NovaProcessOrderLineItemAction;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Apps\Nova\Vouchers\Resources\NovaVoucherClaimsResource;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Support\Helpers\JsonHelper;

/** @property OrderLineItem $resource */
class NovaOrderLineItemResource extends Resource
{
    public static string $model = OrderLineItem::class;

    public static $displayInNavigation = false;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'order';
    }

    public function title(): string
    {
        return (string) $this->resource->order->id;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.order_line_items');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(self::translate('order_line_item.attributes.id'), 'id'),
            Text::make(self::translate('order_line_item.attributes.domain'), 'domain'),
            Text::make(self::translate('order_line_item.attributes.status'), 'status'),
            Text::make(self::translate('order_line_item.attributes.product_name'), 'product_name'),
            Number::make(self::translate('order_line_item.attributes.period'), 'period'),
            Currency::make(self::translate('order_line_item.attributes.gross_price'), 'gross_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->nullable(),
            Currency::make(self::translate('order_line_item.attributes.net_price'), 'net_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->nullable(),
            Text::make(self::translate('order_line_item.attributes.transfer_secret'), 'meta_data')
                ->displayUsing(function () {
                    $decoded = JsonHelper::decodeOrNull($this->resource->meta_data);

                    return $decoded->transfer_secret ?? '';
                })
                ->onlyOnDetail(),
            BelongsTo::make(
                self::translate('order_line_item.attributes.product'),
                'product',
                NovaProductResource::class,
            )->onlyOnDetail(),
            BelongsTo::make(
                self::translate('order_line_item.attributes.subscription'),
                'subscription',
                NovaSubscriptionResource::class,
            )->onlyOnDetail(),
            BelongsTo::make(
                self::translate('order-line-items.relations.parent'),
                'parent',
                self::class,
            )
                ->onlyOnDetail()
                ->canSee(fn (Request $request): bool => $this->resource->parent !== null),
            HasMany::make(
                self::translate('order-line-items.relations.child'),
                'children',
                self::class,
            )
                ->onlyOnDetail()
                ->canSee(fn (Request $request): bool => $this->resource->children->count() > 0),
            HasOne::make(
                self::translate('order-line-items.relations.voucher-claim'),
                'voucherClaim',
                NovaVoucherClaimsResource::class,
            )->onlyOnDetail(),
            Boolean::make(
                self::translate('order_line_item.attributes.should_invoice'),
                'should_invoice',
            )
                ->readonly()
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaProcessOrderLineItemAction::class),
        ];
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToDeleteForSerialization(Request $request): bool
    {
        return false;
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}
