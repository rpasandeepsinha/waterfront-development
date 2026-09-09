<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Hidden;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Invoices\Actions\NovaCreateInvoiceAction;
use Waterfront\Apps\Nova\Invoices\Actions\NovaForcePropagateInvoiceToHarborAction;
use Waterfront\Apps\Nova\Invoices\Filters\NovaInvoicesIsProcessedFilter;
use Waterfront\Apps\Nova\Invoices\Filters\NovaInvoicesProcessedFilter;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Invoices\Services\ComesWithFreeProductInvoiceManager;
use Waterfront\Domain\Invoices\Services\InvoiceToHarborDispatcher;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

/** @property Invoice $resource */
class NovaInvoiceResource extends Resource
{
    public static string $model = Invoice::class;

    public static $perPageViaRelationship = 10;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $search = [
        'title',
        'description',
        'product.slug',
        'product.name',
        'customer.customer_number',
        'subscription_id',
    ];

    public static function getTranslationKey(): string
    {
        return 'invoice';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.invoices');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(
                self::translate('nova-resource-labels.customers'),
                'customer',
                NovaCustomerResource::class
            )->display('customer_number'),
            BelongsTo::make(
                self::translate('product.singular'),
                'product',
                NovaProductResource::class
            )->onlyOnDetail(),
            Text::make(self::translate('invoice.attributes.title'), 'title')
                ->sortable(),
            Text::make(self::translate('invoice.attributes.description'), 'description')
                ->sortable(),
            Text::make(self::translate('invoice.attributes.type'), 'type')
                ->sortable(),
            Text::make(self::translate('invoice.attributes.group_label'), 'group_label')
                ->sortable(),
            Date::make(self::translate('invoice.attributes.start_date'), 'start_date')
                ->displayUsing(fn () => $this->resource->start_date->format(DateTimeFormat::DUTCHNOTIME))
                ->sortable(),
            Date::make(self::translate('invoice.attributes.end_date'), 'end_date')
                ->displayUsing(fn () => $this->resource->end_date->format(DateTimeFormat::DUTCHNOTIME))
                ->sortable(),
            Number::make(self::translate('invoice.attributes.period'), 'period')
                ->help(self::translate('invoice.info.period'))
                ->hideFromIndex(),
            Currency::make(self::translate('invoice.attributes.gross_price'), 'gross_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->rules('min:0'),
            Currency::make(self::translate('invoice.attributes.net_price'), 'net_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->rules('min:0'),
            Currency::make(self::translate('invoice.attributes.net_price_inc_vat'), 'net_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->onlyOnDetail()
                ->displayUsing(function ($amount): string {
                    assert(is_numeric($amount));
                    $amountVat = $amount * ($this->resource->vat_rate / 100);
                    $amountString = '€ ' . number_format(($amount + $amountVat) / 100, 2);
                    return str_replace('.', ',', $amountString);
                }),
            Text::make(self::translate('invoice.attributes.vat_code'), 'vat_code')
                ->onlyOnDetail(),
            Number::make(self::translate('invoice.attributes.vat_rate'), 'vat_rate')
                ->onlyOnDetail()
                ->displayUsing(function ($rate) {
                    assert(is_numeric($rate) || is_string($rate));
                    return $rate . '%';
                }),
            Number::make(self::translate('invoice.attributes.ledger_code'), 'ledger_code')
                ->sortable()
                ->rules(['required', 'numeric', 'between:8000,8100'])
                ->onlyOnDetail(),
            Hidden::make(self::translate('invoice.attributes.paid'), 'paid')->default(false),
            Text::make(
                self::translate('invoice.attributes.subscription.technical_status'),
                'id',
                fn (): ?string => $this->resource->subscription?->technical_status
            ),
            BelongsTo::make(
                self::translate('nova-resource-labels.subscription'),
                'subscription',
                NovaSubscriptionResource::class
            )->display('id'),
            Number::make(
                self::translate('invoice.attributes.subscription.order_id'),
                'id',
                fn (): int|null => $this->resource->subscription?->orderLineItem?->order?->id
            ),
            NovaBoolField::make(
                self::translate('invoice.attributes.subscription.pre_paid'),
                'paid'
            ),
            DateTime::make(self::translate('invoice.attributes.sent_to_harbor_at'), 'sent_to_harbor_at')
                ->displayUsing(fn () => $this->resource->sent_to_harbor_at?->format(DateTimeFormat::DUTCH))
                ->exceptOnForms()
                ->sortable(),
            DateTime::make(self::translate('invoice.attributes.created_at'), 'created_at')
                ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCH))
                ->exceptOnForms()
                ->sortable()
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaInvoicesProcessedFilter::class),
            resolve(NovaInvoicesIsProcessedFilter::class),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            new NovaForcePropagateInvoiceToHarborAction(
                resolve(TranslatorInterface::class),
                resolve(InvoiceToHarborDispatcher::class),
            ),
            new NovaCreateInvoiceAction(
                resolve(TranslatorInterface::class),
                resolve(InvoiceRepository::class),
                resolve(ComesWithFreeProductInvoiceManager::class),
                resolve(AdministrationFeesManager::class),
            ),
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
