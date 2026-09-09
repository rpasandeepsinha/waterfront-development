<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Invoices\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Throwable;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\DTO\AdministrationFees;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\Invoices\Services\ComesWithFreeProductInvoiceManager;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaCreateInvoiceAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ComesWithFreeProductInvoiceManager $comesWithFreeProductInvoiceManager,
        private readonly AdministrationFeesManager $administrationFeesManager,
    ) {
        $this->standalone();
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = resolve(AuthorizationChecker::class);

        $this->canSee(fn (NovaRequest $request): bool => $authorizationChecker->can(Permissions::CREATE_MANUAL_INVOICE_LINE_FOR_SUBSCRIPTION));
        $this->modalSize = '5xl';
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.invoice.create');
    }

    /**
     * @return array<int,NovaBoolField>
     */
    public function fields(NovaRequest $request): array
    {
        $subscriptions = [];
        $products = self::getProductOptions();

        if ($request->viaResource() === NovaCustomerResource::class && is_numeric($request->viaResourceId)) {
            $customerId = $request->viaResourceId;
            $customer = Customer::where('id', $customerId)->firstOrFail();
            $subscriptions = self::getSubscriptionOptions($customer);
        }

        $adminfeesManager = $this->administrationFeesManager;

        /** @phpstan-ignore return.type */
        return [
            Select::make('Subscription', 'subscription')
                ->options($subscriptions)
                ->displayUsingLabels()
                ->searchable()
                ->required()
                ->rules('required'),
            Select::make('Product', 'product')
                ->options($products)
                ->displayUsingLabels()
                ->required()
                ->rules('required')
                ->dependsOn(
                    'subscription',
                    static function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $productId = Subscription::where('id', intval($formData->get('subscription')))
                                ->firstOrFail()->product->id;
                            $field->setValue($productId);
                            $field->immutable();
                            $field->show();
                        }
                    }
                ),
            Text::make('Title')
                ->required()
                ->rules('required')
                ->dependsOn(
                    ['subscription', 'product'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            $field->setValue($subscription->domain ?? $subscription->product->name);
                            $field->immutable(true);
                        }
                    }
                ),
            Text::make('Description')
                ->required()
                ->rules('required')
                ->dependsOn(
                    ['subscription', 'product'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = Subscription::query()->where('id', intval($formData->get('subscription')))
                                ->with('product')
                                ->get()->firstOrFail();

                            $appendable = $subscription->domain !== null ? self::translate('invoice.description.for') . " {$subscription->domain}" : '';
                            $description = sprintf('%s %s', $subscription->product->name, $appendable);
                            $field->setValue($description);
                            $field->immutable(true);
                        }
                    }
                ),

            Date::make('Start date')
                ->required()
                ->rules('required')
                ->dependsOn(
                    ['subscription'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            $lastInvoice = self::getLastDebitInvoice($subscription);
                            if ($lastInvoice !== null) {
                                $field->setValue($lastInvoice->start_date);
                            }
                        }
                    }
                ),
            Date::make('End date')
                ->required()
                ->rules('required')
                ->dependsOn(
                    ['subscription'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            $lastInvoice = self::getLastDebitInvoice($subscription);

                            if ($lastInvoice !== null) {
                                // depending on current billing period of subscription equal to invoice billing period
                                if ($subscription->billing_period === $lastInvoice->period) {
                                    $field->setValue($lastInvoice->end_date);
                                } else {
                                    // not equal, use subscription to populate next invoice date
                                    $field->setValue($lastInvoice->start_date->addMonths($subscription->billing_period));
                                }
                            }
                        }
                    }
                ),
            Currency::make('Gross price', 'gross_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->rules('required', 'min:0')
                ->required()
                ->dependsOn(
                    ['subscription'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            $lastInvoice = self::getLastDebitInvoice($subscription);

                            if ($lastInvoice !== null) {
                                if (
                                    $subscription->billing_period === $lastInvoice->period
                                    && $subscription->product->id === $lastInvoice->product->id
                                ) {
                                    $field->setValue(($lastInvoice->gross_price === 0 || $lastInvoice->gross_price === null) ? 0 : ($lastInvoice->gross_price / 100));
                                }
                            }
                        }
                    }
                ),
            Currency::make('Net price', 'net_price')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits()
                ->rules('required', 'min:0')
                ->required()
                ->dependsOn(
                    ['subscription'],
                    function (Field $field, NovaRequest $request, FormData $formData) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            $lastInvoice = self::getLastDebitInvoice($subscription);

                            if ($lastInvoice !== null) {
                                if (
                                    $subscription->billing_period === $lastInvoice->period
                                    && $subscription->product->id === $lastInvoice->product->id
                                ) {
                                    $field->setValue($lastInvoice->net_price === 0 ? 0 : ($lastInvoice->net_price / 100));
                                }
                            }
                        }
                    }
                ),
            NovaBoolField::make(
                $this->translator->translate('nova-action.manually_add_admin_fees.label'),
                'manually_add_admin_fees'
            )->hide()
                ->dependsOn(
                    ['subscription'],
                    function (Field $field, NovaRequest $request, FormData $formData) use ($adminfeesManager) {
                        if ($formData->get('subscription') !== null) {
                            Assert::integerish($formData->get('subscription'));
                            $subscription = self::getSubscriptionById(intval($formData->get('subscription')));
                            if ($adminfeesManager->shouldBeChargedWithDailyBilling($subscription->customer) === false || $adminfeesManager->getAdministrationFees($subscription->customer) === null) {
                                return;
                            }
                            $field->show();
                        }
                    }
                )->help(
                    $this->translator->translate('nova-action.manually_add_admin_fees.description'),
                ),
        ];
    }

    /**
     * @param Collection<int, Invoice> $models
     *
     * @return ActionResponse|$this
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscriptionId = $fields->get('subscription');
        $startDateString = $fields->get('start_date');
        $endDateString = $fields->get('end_date');
        $grossPrice = $fields->get('gross_price');
        $netPrice = $fields->get('net_price');
        $manuallyAddAdminFees = boolval($fields->get('manually_add_admin_fees'));

        Assert::integerish($subscriptionId);
        Assert::stringNotEmpty($startDateString);
        Assert::stringNotEmpty($endDateString);
        Assert::integerish($grossPrice);
        Assert::integerish($netPrice);
        $startDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $startDateString);
        Assert::isInstanceOf($startDate, CarbonImmutable::class, 'Couldn\'t load the date from the input form.');
        $endDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $endDateString);
        Assert::isInstanceOf($endDate, CarbonImmutable::class, 'Couldn\'t load the date from the input form.');

        try {
            $subscription = self::getSubscriptionById(intval($subscriptionId));
            $invoice = $this->invoiceRepository->createInvoiceForCustomer(
                customer: $subscription->customer,
                product: $subscription->product,
                startDate: $startDate,
                endDate: $endDate,
                grossPrice: intval($grossPrice),
                netPrice: intval($netPrice),
                subscription: $subscription
            );

            if ($this->comesWithFreeProductInvoiceManager->isSubscriptionWhichComesWithFreeProduct($subscription)) {
                $this->comesWithFreeProductInvoiceManager->createInvoice(
                    subscription: $subscription,
                    paidInvoice: $invoice,
                    dispatchInvoiceCreated: false
                );
            }

            if ($manuallyAddAdminFees && $this->administrationFeesManager->shouldBeChargedWithDailyBilling($subscription->customer)) {
                $administrationFees = $this->administrationFeesManager->getAdministrationFees($subscription->customer);
                if ($administrationFees instanceof AdministrationFees) {
                    $this->administrationFeesManager->createAdministrationFeesInvoice(
                        customer: $subscription->customer,
                        administrationFees: $administrationFees,
                        dispatchInvoiceCreated: false,
                    );
                }
            }

            return self::message($this->translator->translate('nova-action.invoice.create-success'));
            /** @phpstan-ignore thecodingmachine.exceptionMustBeRethrown */
        } catch (Throwable $exception) {
            /** @phpstan-ignore-next-line */
            return self::danger('Failed to create invoice, error: ' . $exception->getMessage());
        }
    }

    public static function translate(string $translationKey): string
    {
        $translator = resolve(TranslatorInterface::class);

        return $translator->translate($translationKey);
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    private static function getSubscriptionOptions(Customer $customer): array
    {
        $subscriptions = Subscription::query()->where('customer_id', $customer->id)
            ->with('product')
            ->get();

        return $subscriptions->mapWithKeys(fn (Subscription $subscription) => [
            $subscription->id => 'id:' . $subscription->id . ', ' . $subscription->domain . " ({$subscription->product->name}) [{$subscription->administrative_status}]",
        ])->toArray();
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    private static function getProductOptions(): array
    {
        return Product::query()
            ->has('subscriptions')
            ->with('productGroup')
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(fn (Product $product) => [
                'label' => $product->name,
                'value' => $product->id,
                'group' => $product->productGroup->name,
            ])
            ->toArray();
    }

    private static function getSubscriptionById(int $subscriptionId): Subscription
    {
        return Subscription::query()->where('id', $subscriptionId)
            ->with('product')
            ->firstOrFail();
    }

    private static function getLastDebitInvoice(Subscription $subscription): ?Invoice
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('gross_price', '>', 0)
            ->orderBy('id', 'desc')->first();
    }
}
