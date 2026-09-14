<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\OneTimeServices\Actions\NovaChangeExecutionDateAction;
use Waterfront\Apps\Nova\OneTimeServices\Actions\NovaChangeStatusAction;
use Waterfront\Apps\Nova\OneTimeServices\Actions\NovaInvoiceAction;
use Waterfront\Apps\Nova\OneTimeServices\Filters\NovaInvoicedFilter;
use Waterfront\Apps\Nova\OneTimeServices\Filters\NovaStatusFilter;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

/** @property OneTimeService $resource */
class NovaOneTimeServiceResource extends Resource
{
    public static string $model = OneTimeService::class;

    /**
     * @var mixed[]
     */
    public static $search = [
        'subscription.domain',
        'subscription_id',
        'subscription.customer.customer_number',
        'subscription.customer.last_name',
        'product.name',
        'product.slug',
    ];

    public static function getTranslationKey(): string
    {
        return 'nova-resource-labels.one-time-service';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.one-time-service.plural');
    }

    public function title(): string
    {
        return $this->resource->product->name;
    }

    public function subtitle(): string
    {
        return $this->resource->customer->name . ' - ' . $this->resource->customer->customer_number;
    }

    /**
     * @return array<Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaStatusFilter::class),
            resolve(NovaInvoicedFilter::class),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaChangeExecutionDateAction::class),
            resolve(NovaChangeStatusAction::class),
            resolve(NovaInvoiceAction::class),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $translator = resolve(TranslatorInterface::class);

        return [
            Text::make(
                self::translate('nova-resource-labels.one-time-service.field.customer'),
                resolveCallback: function (): string {
                    $customer = $this->resource->subscription->customer;

                    return (
                        "<a href='"
                        . sprintf(
                            '/nova/resources/nova-customer-resources/%s',
                            $customer->id,
                        )
                        . "' class='link-default'>"
                        . $customer->first_name
                        . ' '
                        . $customer->last_name
                        . '</a>'
                    );
                },
            )->asHtml(),
            BelongsTo::make(
                self::translate('nova-resource-labels.one-time-service.field.subscription'),
                'subscription',
                NovaSubscriptionResource::class,
            )->displayUsing(
                fn (): string => $this->resource->subscription->domain ?? (string) $this->resource->subscription->id,
            ),
            Text::make(
                self::translate('nova-resource-labels.one-time-service.field.subscription.product'),
                resolveCallback: fn (): string => (
                    "<a href='"
                    . sprintf(
                        '/nova/resources/nova-product-resources/%s',
                        $this->resource->subscription->product->id,
                    )
                    . "' class='link-default'>"
                    . $this->resource->subscription->product->name
                    . '</a>'
                ),
            )->asHtml(),
            BelongsTo::make(
                self::translate('nova-resource-labels.one-time-service.field.product'),
                'product',
                NovaProductResource::class,
            ),
            Currency::make(
                self::translate('nova-resource-labels.one-time-service.field.gross-price'),
                'gross_price',
            )
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->onlyOnDetail(),
            Text::make(
                self::translate('nova-resource-labels.one-time-service.field.discount-percentage'),
                'discount_percentage',
                fn (int $discount): string => "$discount%",
            )->onlyOnDetail(),
            Text::make(
                self::translate('nova-resource-labels.one-time-service.field.amount'),
                'amount',
                fn (int $amount): string => "x$amount",
            )->onlyOnDetail(),
            NovaBoolField::make(
                self::translate('nova-resource-labels.one-time-service.field.invoiced'),
                fn () => $this->resource->invoices()->count() > 0,
            ),
            NovaBoolField::make(
                self::translate('nova-resource-labels.one-time-service.field.paid'),
                function () {
                    $invoiceLines = $this->resource->invoices()->get();

                    return (
                        $invoiceLines->isNotEmpty()
                        && $invoiceLines
                            ->filter(
                                fn (Invoice $invoiceLine): bool => $invoiceLine->announced_by_harbor_at === null,
                            )
                            ->count() === 0
                    );
                },
            ),
            Date::make(
                self::translate('nova-resource-labels.one-time-service.field.execution-date'),
                'execution_date',
            )
                ->displayUsing(fn () => $this->resource->execution_date->format(DateTimeFormat::DUTCH))
                ->sortable(),
            Select::make(
                $translator->translate('nova-resource-labels.one-time-service.field.status'),
                'status',
            )
                ->options([
                    OneTimeServiceStatus::OPEN->value => $translator->translate(
                        'one-time-service.status.' . strtolower(OneTimeServiceStatus::OPEN->name),
                    ),
                    OneTimeServiceStatus::IN_PROGRESS->value => $translator->translate(
                        'one-time-service.status.' . strtolower(OneTimeServiceStatus::IN_PROGRESS->name),
                    ),
                    OneTimeServiceStatus::DONE->value => $translator->translate(
                        'one-time-service.status.' . strtolower(OneTimeServiceStatus::DONE->name),
                    ),
                ])
                ->displayUsingLabels()
                ->sortable(),
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
