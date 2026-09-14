<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Exceptions\HelperNotSupported;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Tabs\Tab;
use Laravel\Nova\Tabs\TabsGroup;
use Waterfront\Apps\Nova\Customers\Actions\NovaAnonymizeCustomerAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaLoginAsAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaMarkCustomerAsAbuseAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaOpenCustomerInCompassAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaSetPhoneNumberAction;
use Waterfront\Apps\Nova\Customers\Actions\NovaUpdateVatRateAction;
use Waterfront\Apps\Nova\Customers\Filters\NovaCustomerTypeFilter;
use Waterfront\Apps\Nova\Customers\Lenses\MigratedCustomersLens;
use Waterfront\Apps\Nova\Customers\Rules\VatNovaCode;
use Waterfront\Apps\Nova\General\Resources\NovaNotesResource;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\UseClassNameForFilteringTrait;
use Waterfront\Apps\Nova\Microsoft365\Actions\NovaMicrosoft365CustomTenantAction;
use Waterfront\Apps\Nova\Microsoft365\Resources\NovaMicrosoft365CustomerResource;
use Waterfront\Apps\Nova\Migrations\Resources\NovaMigratedCustomerResource;
use Waterfront\Apps\Nova\OneTimeServices\Resources\NovaOneTimeServiceResource;
use Waterfront\Apps\Nova\Orders\Resources\NovaOrderResource;
use Waterfront\Apps\Nova\Payments\Actions\NovaCreateDirectDebitMandateAction;
use Waterfront\Apps\Nova\Payments\Actions\NovaFindMollieCustomerAction;
use Waterfront\Apps\Nova\Payments\Resources\NovaMollieCustomerResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductDiscountResource;
use Waterfront\Apps\Nova\Products\Resources\NovaProductGroupResource;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaAddVolumeDiscountAction;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaExpiredSubscriptionResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaFreeSubscriptionResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaTransferResource;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property Customer $resource */
class NovaCustomerResource extends Resource
{
    use UseClassNameForFilteringTrait;

    public static string $model = Customer::class;

    /** @var array<mixed> */
    public static $search = [
        'customer_number',
        'organization',
        'first_name',
        'last_name',
        'email',
        'id',
        'uuid',
        'address.zip_code',
        'migratedCustomers.reference_customer_number',
    ];

    /** @var array<mixed> */
    public static $with = [
        'address',
        'customerContacts',
        'microsoft365CustomerInfo',
        'notes',
        'subscriptions',
        'mollieCustomer',
        'migratedCustomers',
    ];

    public static function getTranslationKey(): string
    {
        return 'customer';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.customers');
    }

    public function title(): string
    {
        return $this->resource->name . ' - ' . $this->resource->customer_number;
    }

    /**
     * @return array<int, TabsGroup>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Tab::group(
                fields: [
                    Tab::make(self::translate('customer.singular'), $this->customerFields()),
                    HasMany::make(
                        self::translate('customer.contact.plural'),
                        'customerContacts',
                        NovaCustomerContactResource::class,
                    )->canSee(fn (): bool => $this->resource->customerContacts->isNotEmpty()),
                    HasMany::make(
                        self::translate('nova-resource-labels.migration'),
                        'migratedCustomers',
                        NovaMigratedCustomerResource::class,
                    )->sortable(),
                    HasMany::make(
                        self::translate('nova-resource-labels.microsoft365'),
                        'microsoft365CustomerInfo',
                        NovaMicrosoft365CustomerResource::class,
                    )->sortable(),
                    HasMany::make(
                        self::translate('nova-resource-labels.customer.relation.note'),
                        'notes',
                        NovaNotesResource::class,
                    ),
                ],
            )->withToolbar(),

            Tab::group(self::translate('customer.subscriptions'), [
                /** @uses \Waterfront\Domain\Customers\Models\Customer::paidOrExtensionSubscriptions() */
                HasMany::make(
                    self::translate('subscription.plural'),
                    'paidOrExtensionSubscriptions',
                    NovaSubscriptionResource::class,
                ),
                HasMany::make(
                    self::translate('subscription.expired'),
                    'subscriptions',
                    NovaExpiredSubscriptionResource::class,
                ),
                HasMany::make(
                    self::translate('nova-resource-labels.free-subscriptions'),
                    'subscriptions',
                    NovaFreeSubscriptionResource::class,
                ),
            ]),

            /**
             * Financial fields.
             */
            Tab::group(self::translate('customer.financial'), [
                Tab::make(self::translate('customer.financial'), $this->financialFields($request)),
                HasOne::make(
                    self::translate('nova-resource-labels.mollie_customer'),
                    'mollieCustomer',
                    NovaMollieCustomerResource::class,
                )->onlyOnDetail(),
                BelongsToMany::make(
                    self::translate('customer.relations.product_groups'),
                    'productGroups',
                    NovaProductGroupResource::class,
                )
                    ->fields(
                        fn (): array => [
                            Number::make(self::translate('customer.discount'), 'discount')
                                ->min(0)
                                ->max(100)
                                ->step(0.01)
                                ->help(self::translate('customer.info.discount')),
                        ],
                    )
                    ->singularLabel(self::translate('product-group.singular')),
                HasMany::make(
                    self::translate('nova-resource-labels.orders'),
                    'orders',
                    NovaOrderResource::class,
                ),
                HasMany::make(
                    self::translate('nova-resource-labels.one-time-service.plural'),
                    'oneTimeServices',
                    NovaOneTimeServiceResource::class,
                ),
            ]),

            //Product transfers (internal subscription transfers)
            Tab::group(self::translate('customer.transfers'), [
                HasMany::make(
                    self::translate('transfer.incoming'),
                    'toCustomerTransfers',
                    NovaTransferResource::class,
                ),
                HasMany::make(
                    self::translate('transfer.outgoing'),
                    'fromCustomerTransfers',
                    NovaTransferResource::class,
                ),
            ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaSetPhoneNumberAction::class),
            resolve(NovaUpdateVatRateAction::class),
            resolve(NovaLoginAsAction::class),
            resolve(NovaOpenCustomerInCompassAction::class),
            resolve(NovaMicrosoft365CustomTenantAction::class),
            resolve(NovaMarkCustomerAsAbuseAction::class),
            resolve(NovaAnonymizeCustomerAction::class)
                ->onlyOnDetail()
                ->confirmButtonText(self::translate('nova-action.anonymize-customer.confirmbutton')),
            resolve(NovaFindMollieCustomerAction::class),
            resolve(NovaCreateDirectDebitMandateAction::class)->onlyOnDetail(),
            resolve(NovaAddVolumeDiscountAction::class)->onlyOnDetail(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaCustomerTypeFilter::class),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    /**
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            new MigratedCustomersLens(),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private function customerFields(): array
    {
        $paymentTypeCases = array_map(
            fn (PaymentType $paymentType): string => $paymentType->value,
            PaymentType::cases(),
        );

        return [
            Number::make(self::translate('customer.attributes.customer_number'), 'customer_number')
                ->required()
                ->showOnIndex()
                ->copyable()
                ->creationRules('required', 'unique:customers')
                ->updateRules('required', 'unique:customers,customer_number,{{resourceId}}'),
            Text::make(self::translate('customer.attributes.uuid'), 'uuid')
                ->required()
                ->hideWhenUpdating()
                ->hideWhenCreating()
                ->hideFromIndex()
                ->copyable()
                ->creationRules('unique:customers')
                ->updateRules('unique:customers,uuid,{{resourceId}}'),
            Text::make(self::translate('customer.attributes.name'), 'contact_name')->exceptOnForms(),
            Text::make(self::translate('customer.attributes.email'), 'email')
                ->copyable()
                ->required()
                ->rules('required', 'email'),
            Text::make(self::translate('customer.attributes.organization'), 'organization')
                ->sortable()
                ->hideFromDetail(),
            Text::make(self::translate('customer.attributes.department'), 'department')->sortable()->onlyOnForms(),
            Text::make(
                self::translate('customer.attributes.organization-department'),
                function () {
                    $organization = $this->resource->organization ?? '';
                    $department = $this->resource->department ?? '';

                    return $organization . ' - ' . $department;
                },
            )->onlyOnDetail(),
            NovaBoolField::make(
                self::translate('customer.attributes.is_abuse'),
                'is_abuse',
            )->onlyOnDetail(),
            NovaBoolField::make(
                self::translate('customer.attributes.migrated_customer'),
                fn (): bool => $this->resource->migratedCustomers()->exists(),
            )->exceptOnForms(),
            NovaBoolField::make(self::translate('customer.is-microsoft365-customer'))
                ->exceptOnForms()
                ->resolveUsing(fn () => $this->resource->microsoft365CustomerInfo()->exists())
                ->hideFromDetail(fn () => ! $this->resource->microsoft365CustomerInfo()->exists()),
            Select::make(self::translate('customer.attributes.gender'), 'gender')
                ->options(
                    [
                        Gender::MALE->value => self::translate('customer.attributes.gender.male'),
                        Gender::FEMALE->value => self::translate('customer.attributes.gender.female'),
                        Gender::NEUTRAL->value => self::translate('customer.attributes.gender.undisclosed'),
                    ],
                )
                ->required()
                ->onlyOnForms(),
            Text::make(self::translate('customer.attributes.first_name'), 'first_name')
                ->required()
                ->rules('required')
                ->onlyOnForms(),
            Text::make(self::translate('customer.attributes.last_name'), 'last_name')
                ->required()
                ->rules('required')
                ->onlyOnForms(),
            Text::make(self::translate('customer.attributes.phone'), 'phone_number')->onlyOnDetail(),
            NovaBoolField::make(self::translate('customer.attributes.is-company'))
                ->exceptOnForms()
                ->hideFromIndex()
                ->resolveUsing(fn () => $this->resource->isCompany())
                ->hideFromDetail(fn () => $this->resource->isCompany() === false),
            Select::make(self::translate('customer.attributes.payment_type'), 'payment_type')
                ->options(array_combine($paymentTypeCases, $paymentTypeCases))
                ->hideFromIndex(),
            DateTime::make(self::translate('customer.attributes.anonymized_at'), 'anonymized_at')
                ->onlyOnDetail()
                ->hideFromDetail(fn () => $this->resource->anonymized_at === null)
                ->displayUsing(fn () => $this->resource->anonymized_at?->format(DateTimeFormat::DUTCH)),
            Date::make(self::translate('nova-resource-customer.data_last_confirmed_at'), 'data_last_confirmed_at')
                ->onlyOnDetail()
                ->hideFromDetail(fn () => $this->resource->data_last_confirmed_at === null)
                ->displayUsing(fn () => $this->resource->data_last_confirmed_at?->format(DateTimeFormat::DUTCH)),
            DateTime::make(self::translate('nova-resource-labels.created-at'), 'created_at')
                ->onlyOnDetail()
                ->hideFromDetail(fn () => $this->resource->created_at === null)
                ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCH)),
        ];
    }

    /**
     * @throws HelperNotSupported
     *
     * @return array<int, Field>
     */
    private function financialFields(Request $request): array
    {
        $paymentTermDefault = Config::get('constants.payment-terms.default');
        $paymentTermExtended = Config::get('constants.payment-terms.extended');
        assert(is_int($paymentTermDefault));
        assert(is_int($paymentTermExtended));

        /** @var string $customerNumberString */
        $customerNumberString = $request->input('customer_number', '0');

        return [
            Text::make(self::translate('customer.attributes.coc_number'), 'coc_number')->hideFromIndex(),
            Text::make(self::translate('customer.attributes.vat_number'), 'vat_number')
                ->rules('nullable', 'string', new VatNovaCode(intval($customerNumberString)))
                ->hideFromIndex(),
            Number::make(self::translate('customer.attributes.vat_rate'), 'vat_rate')->exceptOnForms()->onlyOnDetail(),
            NovaBoolField::make(self::translate('customer.attributes.icp'), 'icp')->hideFromIndex(),
            Text::make(
                self::translate('customer.attributes.purchase_reference'),
                'purchase_reference',
            )->hideFromIndex(),

            Text::make(self::translate('customer.attributes.invoice_history_url'), 'invoice_history_url')
                ->rules('nullable', 'url', 'max:2000')
                ->onlyOnForms(),
            URL::make(
                self::translate('customer.attributes.invoice_history_url'),
                'invoice_history_url',
            )->onlyOnDetail(),

            Text::make(self::translate('customer.attributes.admin_url'), 'admin_url')
                ->rules('nullable', 'url')
                ->onlyOnForms(),
            URL::make(self::translate('customer.attributes.admin_url'), 'admin_url')->onlyOnDetail(),

            Select::make(self::translate('customer.attributes.terms_of_payment'), 'terms_of_payment')
                ->rules('required', 'integer')
                ->options([
                    $paymentTermDefault => (string) $paymentTermDefault,
                    $paymentTermExtended => (string) $paymentTermExtended,
                ])
                ->hideFromIndex(),
            Currency::make(self::translate('customer.attributes.credit_limit'), 'credit_limit')
                ->required()
                ->rules('required')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->hideFromIndex(),
            NovaBoolField::make(
                self::translate('customer.attributes.has_direct_debit'),
                'has_direct_debit',
            )->onlyOnDetail(),
            BelongsTo::make(
                self::translate('customer.relations.product_discounts'),
                'productDiscount',
                NovaProductDiscountResource::class,
            )->onlyOnDetail(),
            HasMany::make(
                self::translate('customer.address.singular'),
                'address',
                NovaCustomerAddressResource::class,
            ),
        ];
    }
}
