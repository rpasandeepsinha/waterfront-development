<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Actions\NovaCustomerWalletDownloadOverviewCsvAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaCustomerWalletDownloadRefundCsvAction;
use Waterfront\Apps\Nova\Customers\Filters\NovaWalletRefundFilter;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Infra\Translation\TranslatorInterface;

/** @property CustomerWallet $resource */
class NovaCustomerWalletResource extends Resource
{
    public static string $model = CustomerWallet::class;

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'customer.wallet';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.customer-wallet');
    }

    public static function availableForNavigation(Request $request): bool
    {
        return Config::get('app.theme') === 'versio';
    }

    public function title(): string
    {
        return (string) $this->resource->customer->customer_number;
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        $translator = resolve(TranslatorInterface::class);

        return [
            new NovaWalletRefundFilter(NovaWalletRefundFilter::FILTER_IS_REQUESTED, $translator),
            new NovaWalletRefundFilter(NovaWalletRefundFilter::FILTER_IS_CSV_DOWNLOADED, $translator),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaCustomerWalletDownloadRefundCsvAction::class),
            resolve(NovaCustomerWalletDownloadOverviewCsvAction::class)->standalone(),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            BelongsTo::make(self::translate('customer.singular'), 'customer', NovaCustomerResource::class)->readonly(
                static fn (NovaRequest $request) => $request->isUpdateOrUpdateAttachedRequest(),
            ),
            Currency::make(self::translate('customer.wallet.attributes.amount'), 'amount')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->rules('required', 'min:0'),
            Text::make(self::translate('customer.wallet.attributes.bank_account_number'), 'bank_account_number')
                ->hideWhenCreating()
                ->hideFromIndex(),
            Text::make(self::translate('customer.wallet.attributes.bank_account_name'), 'bank_account_name')
                ->hideWhenCreating()
                ->hideFromIndex(),
            DateTime::make(self::translate('customer.wallet.attributes.refund_requested_at'), 'refund_requested_at')
                ->hideWhenCreating()
                ->nullable()
                ->sortable(),
            DateTime::make(self::translate('customer.wallet.attributes.csv_downloaded_at'), 'csv_downloaded_at')
                ->hideWhenCreating()
                ->nullable()
                ->sortable(),
        ];
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
