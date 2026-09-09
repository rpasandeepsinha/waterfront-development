<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Products\Fields\NovaProductSelectField;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Actions\CreateOneTimeServiceAction;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Helpers\Money;
use Webmozart\Assert\Assert;

class NovaCreateOneTimeServiceAction extends NovaSubscriptionAction
{
    public $modalSize = '4xl';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly CreateOneTimeServiceAction $createOneTimeServiceAction,
        private readonly OneTimeServiceInvoiceService $oneTimeServiceInvoiceService,
    ) {
        $this->confirmText('');
        $this->canSee(
            fn (NovaRequest $request): bool =>
             $this->onlyForSingleCustomer($request)
        );
    }

    /**
     * Get the displayable name of the action.
     */
    public function name(): string
    {
        return $this->translator->translate('nova-action.one_time_service_invoicing.name');
    }

    /**
     * @return array<int, Heading|Select|Number|Text|Date>
     */
    public function fields(NovaRequest $request): array
    {
        $productGroup = ProductGroup::where('slug', ProductGroupType::ONE_TIME_SERVICE)
            ->first();

        // If the product group does not exist or no products are in the group.
        // Show a setup required message.
        if (
            $productGroup === null
            || $productGroup->products->count() === 0
        ) {
            return [
                Heading::make("<p class='text-red-600'>{$this->translator->translate('nova-action.one_time_service_invoicing.setup')}</p>")
                    ->asHtml(),
            ];
        }

        $selectedSubscriptions = $this->getSelectedSubscriptionsFromRequest($request);

        $now = CarbonImmutable::now();
        return [
            $this->generateOverviewOfSelectedSubscriptions($selectedSubscriptions, $this->translator),
            NovaProductSelectField::make('service_product', $productGroup)->displayUsingLabels()->required(),
            Number::make($this->translator->translate('nova-action.one_time_service_invoicing.amount'), 'amount')
                ->min(1)
                ->default(1)
                ->required(),

            Number::make($this->translator->translate('nova-action.one_time_service_invoicing.discount_percentage'), 'discount_percentage')
                ->min(0)
                ->max(100)
                ->default(0)
                ->required(),

            Date::make($this->translator->translate('nova-action.one_time_service_invoicing.execution_date'), 'execution_date')
                ->min($now)
                ->default($now->format(DateTimeFormat::DUTCH))
                ->required(),
            Select::make(
                $this->translator->translate('nova-resource-labels.one-time-service.field.status'),
                'status',
            )->options([
                OneTimeServiceStatus::OPEN->value => $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::OPEN->name)),
                OneTimeServiceStatus::IN_PROGRESS->value => $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::IN_PROGRESS->name)),
                OneTimeServiceStatus::DONE->value => $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::DONE->name)),
            ])->displayUsingLabels()
                ->required()
                ->sortable(),

            Select::make($this->translator->translate('nova-action.one-time-service.invoice-now'), 'invoice_now')
                ->options([
                    'yes' => $this->translator->translate('nova-action.one-time-service.invoice-now.yes'),
                    'no' => $this->translator->translate('nova-action.one-time-service.invoice-now.no'),
                ])
                ->displayUsingLabels()
                ->required()
                ->help($this->translator->translate('nova-action.one-time-service.invoice-now.help')),

            Text::make($this->translator->translate('nova-action.one_time_service_invoicing.comment'), 'comment')
                ->help($this->translator->translate('nova-action.one_time_service_invoicing.comment.help')),

            $this->invoiceLinesPreviewField($selectedSubscriptions),
        ];
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $oneTimeServiceContexts = $this->getOneTimeServiceContextsFromRequest($models, $fields);

        if ($oneTimeServiceContexts->isEmpty()) {
            return self::danger(
                $this->translator->translate('nova-action.error.one_time_service_invoicing')
            );
        }

        // Create the one time services
        $otsCollection = $this->createOneTimeServiceAction->execute($oneTimeServiceContexts);

        $invoiceNow = $fields->get('invoice_now');
        Assert::stringNotEmpty($invoiceNow);

        if ($invoiceNow === 'yes') {
            $this->oneTimeServiceInvoiceService->createFromCollection($otsCollection);
        }

        return self::message(
            $this->translator->translate('nova-action.success.one_time_service_invoicing')
        );
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    private function invoiceLinesPreviewField(Collection $subscriptions): Heading
    {
        return Heading::make('invoice_preview')
            ->hide()
            ->asHtml()
            ->dependsOn(
                ['service_product', 'amount', 'discount_percentage', 'execution_date', 'status'],
                fn (Heading $field, NovaRequest $request, FormData $formData)
                    => $this->updatePreviewOfInvoiceLines($field, $formData, $subscriptions)
            );
    }

    /**
     * @param FormData<string, string>      $formData
     * @param Collection<int, Subscription> $subscriptions
     */
    private function updatePreviewOfInvoiceLines(Heading $field, FormData $formData, Collection $subscriptions): void
    {
        $oneTimeServiceContexts = $this->getOneTimeServiceContextsFromRequest($subscriptions, $formData);

        if ($oneTimeServiceContexts->isEmpty()) {
            return;
        }

        $field->withMeta([
            'value' => $this->generatePreviewOfInvoiceLinesAsHtml($oneTimeServiceContexts),
        ]);
        $field->show();
    }

    /**
     * @param Collection<int, OneTimeServiceContext> $oneTimeServiceContexts
     */
    private function generatePreviewOfInvoiceLinesAsHtml(Collection $oneTimeServiceContexts): string
    {
        $invoiceLinesPreview = $this->oneTimeServiceInvoiceService->getInvoiceLinesPreview($oneTimeServiceContexts);
        $rows = '';
        $totalPrice = 0;
        foreach ($invoiceLinesPreview as $invoiceLinePreview) {
            $totalPrice += $invoiceLinePreview['price'] * $invoiceLinePreview['amount'];
            $price = Money::format($invoiceLinePreview['price']);
            $rows .= <<<ROW
<tr>
    <td>{$invoiceLinePreview['subscriptionId']}</td>
    <td>{$invoiceLinePreview['domain']}</td>
    <td>{$invoiceLinePreview['title']}</td>
    <td>{$invoiceLinePreview['amount']}x</td>
    <td>{$price}</td>
</tr>
ROW;
        }

        $tableHeaderTitle = $this->translator->translate('nova-action.one_time_service_invoicing.header_preview');
        $totalPrice = Money::format($totalPrice);

        return <<<TABLE
<h3 class="text-xl">{$tableHeaderTitle}</h3>
<hr />
<table class='w-full divide-y divide-gray-100 dark:divide-gray-700'>
<thead class='bg-gray-50 dark:bg-gray-800'>
    <tr>
        <td>{$this->translator->translate('subscription.singular')}</td>
        <td>{$this->translator->translate('invoice.attributes.domain')}</td>
        <td>{$this->translator->translate('invoice.attributes.product_name')}</td>
        <td>{$this->translator->translate('nova-action.one_time_service_invoicing.amount')}</td>
        <td>{$this->translator->translate('invoice.attributes.net_price')}</td>
    </tr>
</thead>
<tbody class='divide-y divide-gray-100 dark:divide-gray-700'>
    {$rows}
</tbody>
<tbody>
    <tr>
        <td class="text-right" colspan="4">total</td>
        <td>{$totalPrice}</td>
    </tr>
</tbody>
</table>
TABLE;
    }

    /**
     * @param Collection<int, Subscription>         $subscriptions
     * @param FormData<string, string>|ActionFields $data
     *
     * @return Collection<int, OneTimeServiceContext>
     */
    private function getOneTimeServiceContextsFromRequest(
        Collection $subscriptions,
        FormData|ActionFields $data
    ): Collection {
        $contexts = new Collection();

        if (
            $data->get('service_product') === null
            || $data->get('amount') === null
            || $data->get('discount_percentage') === null
            || $data->get('execution_date') === null
            || $data->get('status') === null
        ) {
            return $contexts;
        }

        $serviceProductUuid = $data->get('service_product');
        Assert::stringNotEmpty($serviceProductUuid);
        $serviceProduct = $this->productRepository->findProductByUuid($serviceProductUuid);

        $amount = $data->get('amount');
        Assert::integerish($amount);
        $amount = intval($amount);

        $discountPercentage = $data->get('discount_percentage');
        Assert::integerish($discountPercentage);
        $discountPercentage = intval($discountPercentage);

        $executionDateString = $data->get('execution_date');
        Assert::stringNotEmpty($executionDateString);
        $executionDate = CarbonImmutable::createFromFormat(DateTimeFormat::DATE, $executionDateString);
        Assert::isInstanceOf($executionDate, CarbonImmutable::class, 'Couldn\'t load the execution date from the input form.');

        $statusString = $data->get('status');
        Assert::stringNotEmpty($statusString, $this->translator->translate('nova-action.one-time-service.change-status.error.empty'));
        $status = OneTimeServiceStatus::from($statusString);

        $comment = $data->get('comment');
        Assert::nullOrStringNotEmpty($comment);

        foreach ($subscriptions as $subscription) {
            $contexts->add(
                new OneTimeServiceContext(
                    subscription: $subscription,
                    product: $serviceProduct,
                    amount: $amount,
                    discountPercentage: $discountPercentage,
                    status: $status,
                    executionDate: $executionDate,
                    comment: $comment,
                    grossPrice: null
                )
            );
        }

        return $contexts;
    }
}
