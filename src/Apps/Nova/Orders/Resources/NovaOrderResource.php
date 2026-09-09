<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Orders\Resources;

use Illuminate\Http\Request;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasManyThrough;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Orders\Actions\NovaBillOrderAction;
use Waterfront\Apps\Nova\Orders\Actions\NovaProcessOrderAction;
use Waterfront\Apps\Nova\Orders\Filters\NovaOrderStatusFilter;
use Waterfront\Apps\Nova\Payments\Resources\NovaPaymentResource;
use Waterfront\Apps\Nova\Vouchers\Resources\NovaVoucherClaimsResource;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property Order $resource */
class NovaOrderResource extends Resource
{
    public static string $model = Order::class;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'id',
        'uuid',
        'customer_id',
    ];

    /**
     * @var array<mixed>
     */
    public static $with = ['customer', 'payments', 'lineItems'];

    public static function getTranslationKey(): string
    {
        return 'order';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.orders');
    }

    /**
     * @return array<Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [resolve(NovaOrderStatusFilter::class)];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaProcessOrderAction::class),
            resolve(NovaBillOrderAction::class),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate('order.attributes.id'), 'uuid')->sortable(),
            BelongsTo::make(
                self::translate('nova-resource-labels.customers'),
                'customer',
                NovaCustomerResource::class
            ),
            Text::make(self::translate('order.attributes.status'), 'status')
                ->hideWhenUpdating()
                ->displayUsing(
                    function ($value) {
                        assert(is_string($value));
                        return self::translate(
                            sprintf('orders.order_status.%s', $value)
                        );
                    }
                )->sortable(),
            Currency::make(self::translate('order.attributes.total_amount'), 'total_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->sortable(),
            Currency::make(self::translate('order.attributes.administration_fees'), 'administration_fees')
                ->currency('EUR')
                ->asMinorUnits()
                ->onlyOnDetail(),
            NovaBoolField::make(self::translate('order.attributes.is_paid'), 'is_paid'),
            NovaBoolField::make(self::translate('order.attributes.is_invoiced'), 'is_invoiced'),
            HasMany::make(
                self::translate('order.attributes.payments'),
                'payments',
                NovaPaymentResource::class
            )->onlyOnDetail(),
            HasMany::make(
                self::translate('order.attributes.order_line_items'),
                'lineItems',
                NovaOrderLineItemResource::class
            )->onlyOnDetail(),
            Text::make(self::translate('order.attributes.ordered_by_uuid'), 'ordered_by_uuid')
                ->onlyOnDetail(),
            Text::make(self::translate('order.attributes.ordered_by_metadata'), 'ordered_by_metadata')
                ->displayUsing(
                    function ($value) {
                        if (! is_string($value) || strlen($value) === 0) {
                            return self::translate('order.attributes.ordered_by_metadata.customer');
                        }

                        $data = json_decode($value, true);

                        if (! is_array($data) || ! array_key_exists('schemaId', $data)) {
                            return null;
                        }

                        if ($data['schemaId'] === SchemaId::EMPLOYEE->value) {
                            $email = null;
                            if (array_key_exists('email', $data)) {
                                $email = $data['email'];
                            }

                            return sprintf(self::translate('order.attributes.ordered_by_metadata.' . SchemaId::EMPLOYEE->value), $email);
                        }

                        return self::translate('order.attributes.ordered_by_metadata.' . $data['schemaId']);
                    }
                ),
            DateTime::make(self::translate('nova-resource-labels.created-at'), 'created_at')
                ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCH))
                ->sortable(),

            HasManyThrough::make(
                self::translate('order.attributes.voucher-claims'),
                'voucherClaims',
                NovaVoucherClaimsResource::class,
            )->onlyOnDetail()->readonly(),
        ];
    }

    /**
     * @return array<int, HtmlCard>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            new HtmlCard()
                ->width('full')
                ->html('<p class="text-80 font-light mt-2">' . self::translate('language.nova_info.searching_orders') . '</p>'),
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
