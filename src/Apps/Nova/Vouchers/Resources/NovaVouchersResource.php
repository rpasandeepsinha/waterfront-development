<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Vouchers\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductGroupResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property Voucher $resource */
class NovaVouchersResource extends Resource
{
    public static string $model = Voucher::class;

    public static $globallySearchable = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'display_name',
        'internal_name',
        'code',
        'amount',
    ];

    public static function getTranslationKey(): string
    {
        return 'voucher';
    }

    public function title(): string
    {
        return $this->resource->internal_name;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.vouchers');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(self::translate('voucher.attributes.display_name'), 'display_name')
                ->sortable()
                ->rules('required')
                ->readonly(fn ($request) => $request->isUpdateOrUpdateAttachedRequest()),
            Text::make(self::translate('voucher.attributes.internal_name'), 'internal_name')
                ->sortable()
                ->rules('required')
                ->readonly(fn ($request) => $request->isUpdateOrUpdateAttachedRequest()),
            Textarea::make(self::translate('voucher.attributes.description'), 'description')
                ->rules('required')
                ->hideFromIndex(),
            Text::make(self::translate('voucher.attributes.code'), 'code')
                ->sortable()
                ->readonly(fn ($request) => $request->isUpdateOrUpdateAttachedRequest())
                ->withMeta(['value' => $this->resource->code ?? $this->generateCode()])
                ->rules('required'),
            Select::make(self::translate('voucher.attributes.amount_type'), 'amount_type')
                ->options(array_column(VoucherAmountType::cases(), 'value', 'value'))
                ->rules('required')
                ->readonly(fn ($request) => $request->isUpdateOrUpdateAttachedRequest()),
            Number::make(self::translate('voucher.attributes.amount'), 'amount')
                ->readonly(fn ($request) => $request->isUpdateOrUpdateAttachedRequest())
                ->rules('required'),
            Number::make(self::translate('voucher.attributes.max_claims'), 'max_claims')
                ->sortable()
                ->min(0)
                ->nullable()
                ->help(self::translate('voucher.attributes.max_claims_help'))
                ->displayUsing(fn () => $this->resource->max_claims ?? self::translate('voucher.status.infinite')),
            Number::make(
                self::translate('voucher.attributes.amount_of_claims'),
                'claims',
            )
                ->displayUsing(fn (): int => $this->resource->claims()->count())
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(self::translate('voucher.attributes.amount_left'), 'amount_left')
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsing(fn () => $this->generateAmountLeft()),
            Text::make(self::translate('voucher.attributes.status'), 'status')
                ->sortable()
                ->exceptOnForms()
                ->displayUsing(fn () => $this->generateStatus()),
            Number::make(self::translate('subscription.attributes.contract_period'), 'contract_period')
                ->hideWhenUpdating()
                ->nullable()
                ->hideFromIndex(),
            Number::make(self::translate('subscription.attributes.billing_period'), 'billing_period')
                ->hideWhenUpdating()
                ->nullable()
                ->hideFromIndex(),
            BelongsTo::make(self::translate('voucher.relations.product'), 'product', NovaProductResource::class)
                ->hideFromIndex()
                ->hideWhenUpdating()
                ->nullable(),
            BelongsTo::make(
                self::translate('voucher.relations.product_group'),
                'productGroup',
                NovaProductGroupResource::class,
            )
                ->hideFromIndex()
                ->hideWhenUpdating(),
            DateTime::make(self::translate('voucher.attributes.expiration_date'), 'expiration_date')
                ->displayUsing(fn () => $this->resource->expiration_date?->format(DateTimeFormat::DUTCHNOTIME))
                ->nullable(),
            NovaBoolField::make(self::translate('voucher.attributes.apply_with_discount'), 'apply_with_discount')
                ->hideFromIndex()
                ->hideWhenUpdating()
                ->help(self::translate('voucher.attributes.apply_with_discount_help')),
            NovaBoolField::make(
                self::translate('voucher.attributes.allow_multiple_claims_same_customer'),
                'allow_multiple_claims_same_customer',
            )
                ->hideFromIndex()
                ->hideWhenUpdating()
                ->help(
                    self::translate('voucher.attributes.allow_multiple_claims_same_customer_help'),
                ),
            HasMany::make(
                self::translate('voucher.relations.claims'),
                'claims',
                NovaVoucherClaimsResource::class,
            ),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    private function generateCode(): string
    {
        return Str::random(15);
    }

    private function generateStatus(): string
    {
        if (null !== $this->resource->expiration_date && $this->resource->expiration_date < CarbonImmutable::now()) {
            return self::translate('voucher.status.expired');
        }

        if (null === $this->resource->max_claims) {
            return self::translate('voucher.status.active');
        }

        if ($this->resource->max_claims === $this->resource->claims()->count()) {
            return self::translate('voucher.status.all_spend');
        }

        if ($this->resource->max_claims < $this->resource->claims()->count()) {
            return self::translate('voucher.status.over_spend');
        }

        return self::translate('voucher.status.active');
    }

    private function generateAmountLeft(): int|string
    {
        if (null === $this->resource->max_claims) {
            return self::translate('voucher.status.infinite');
        }

        return $this->resource->max_claims - $this->resource->claims()->count();
    }
}
