<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\DTO\NextInvoicePriceDTO;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class InvoiceRepository
{
    public function __construct(
        private readonly CustomerVatService $vatService,
        private readonly Dispatcher $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function findById(int $id): ?Invoice
    {
        return Invoice::where('id', $id)->first();
    }

    public function createNextSubscriptionInvoice(
        Subscription $subscription,
        NextInvoicePriceDTO $nextInvoicePrice,
        bool $dispatchInvoiceCreated = true,
    ): Invoice {
        $customer = $subscription->customer;
        $product = $nextInvoicePrice->product;
        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $appendable = $subscription->domain !== null ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}" : '';
        $description = sprintf(
            '%s %s',
            $product->name,
            $appendable,
        );

        $invoice = new Invoice();
        $invoice->subscription_id    = $subscription->id;
        $invoice->customer_id        = $customer->id;
        $invoice->product_id         = $product->id;
        $invoice->ledger_code        = $product->productGroup->ledger_code;
        $invoice->vat_code           = $customerVatDTO->vatCode;
        $invoice->vat_rate           = $customerVatDTO->vatRate;
        $invoice->title              = $subscription->domain ?? $product->name;
        $invoice->description        = $description;
        $invoice->group_label        = $subscription->domain;
        $invoice->type               = InvoiceLine::TYPE_DEFAULT;
        $invoice->paid               = false;
        $invoice->start_date         = $nextInvoicePrice->startDate;
        $invoice->end_date           = $nextInvoicePrice->endDate;
        $invoice->period             = $nextInvoicePrice->billingPeriod;
        $invoice->gross_price        = $nextInvoicePrice->grossPrice;
        $invoice->net_price          = $nextInvoicePrice->netPrice;
        $invoice->save();

        if ($dispatchInvoiceCreated) {
            $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($invoice, false));
        }

        return $invoice;
    }

    public function createOrderLineSubscriptionInvoice(
        Customer $customer,
        OrderLineItem $orderLineItem,
        ?string $prepaidReference = null,
    ): Invoice {
        $subscription = $orderLineItem->subscription;
        Assert::notNull($subscription, 'OrderLineItem must have a subscription to be billable');

        $product = $orderLineItem->product;
        Assert::notNull($product, 'OrderLineItem must have a product to be billable');

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $appendable = $orderLineItem->domain !== null ? $this->translator->translate('invoice.description.for') . " {$orderLineItem->domain}" : '';
        $description = sprintf(
            '%s %s',
            $product->name,
            $appendable,
        );

        $invoice = new Invoice();
        $invoice->subscription_id    = $subscription->id;
        $invoice->customer_id        = $customer->id;
        $invoice->product_id         = $product->id;
        $invoice->ledger_code        = $product->productGroup->ledger_code;
        $invoice->vat_code           = $customerVatDTO->vatCode;
        $invoice->vat_rate           = $customerVatDTO->vatRate;
        $invoice->title              = $orderLineItem->domain ?? $product->name;
        $invoice->description        = $description;
        $invoice->group_label        = $orderLineItem->domain;
        $invoice->type               = InvoiceLine::TYPE_DEFAULT;
        $invoice->paid               = $prepaidReference !== null;
        $invoice->prepaid_reference  = $prepaidReference;
        $invoice->start_date         = $subscription->start_date;
        $invoice->end_date           = $subscription->next_billing_date;
        $invoice->period             = $orderLineItem->billing_period;
        $invoice->gross_price        = $orderLineItem->gross_price;
        $invoice->net_price          = $orderLineItem->net_price;
        $invoice->save();

        return $invoice;
    }

    /**
     * Create invoice for the given subscription.
     *
     *
     * @throws InvalidCountryCodeException
     */
    public function create(Subscription $subscription, ?CarbonImmutable $startDate = null, bool $paid = false, ?int $grossPrice = null, ?int $netPrice = null, bool $dispatchInvoiceCreated = true): Invoice
    {
        $invoice = $this->createInvoice(
            subscription: $subscription,
            startDate: $startDate,
            paid: $paid,
            grossPrice: $grossPrice,
            netPrice: $netPrice
        );

        if ($dispatchInvoiceCreated) {
            $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($invoice, false));
        }

        return $invoice;
    }

    /**
     * Create a new invoice for the given subscription.
     *
     *
     * @throws InvalidCountryCodeException
     */
    public function createInvoice(Subscription $subscription, ?CarbonImmutable $startDate = null, bool $paid = false, ?int $grossPrice = null, ?int $netPrice = null): Invoice
    {
        $subscription->loadMissing(
            [
                'customer',
                'product.productGroup',
            ]
        );
        $customer = $subscription->customer;

        $productGroup = $subscription->product->productGroup;

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);
        $appendable = $subscription->domain !== null ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}" : '';
        $description = sprintf(
            '%s %s',
            $subscription->product->name,
            $appendable,
        );

        return Invoice::create(
            [
                'subscription_id'    => $subscription->id,
                'customer_id'        => $customer->id,
                'product_id' => $subscription->product->id,
                'vat_code' => $customerVatDTO->vatCode,
                'vat_rate' => $customerVatDTO->vatRate,
                'ledger_code' => $productGroup->ledger_code,
                'paid' => $paid,
                'domain' => $subscription->domain,
                'start_date' => $startDate ?? $subscription->start_date,
                'end_date' => $subscription->next_billing_date,
                'period' => $subscription->billing_period,
                'gross_price' => $grossPrice ?? $subscription->gross_price,
                'net_price' => $netPrice ?? $subscription->net_price,
                'title' => $subscription->domain ?? $subscription->product->name,
                'description' => $description,
                'group_label' => $subscription->domain,
                'type' => InvoiceLine::TYPE_DEFAULT,
                'credit_reason' => null,
                'prepaid_reference' => $this->findPrepaidPayment($paid, $subscription),
            ]
        );
    }

    /**
     * Create a new invoice for the given changed subscription.
     *
     * @throws InvalidCountryCodeException
     */
    public function createInvoiceForChangedSubscription(
        Subscription $subscription,
        int $charge,
        ProductChangeType $changeType,
        ?CarbonImmutable $startDate = null,
        bool $paid = false,
    ): Invoice {
        $endCustomer = $subscription->customer;
        $customer = $subscription->customer;

        $productGroup = $subscription->product->productGroup;

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);
        $appendable = $subscription->domain !== null ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}" : '';
        $description = sprintf(
            '%s (%s) %s',
            $subscription->product->name,
            $changeType->value,
            $appendable,
        );

        $startDate ??= $subscription->start_date;

        return Invoice::create(
            array_merge(
                [
                    'domain' => $subscription->domain,
                    'end_date' => $subscription->next_billing_date,
                    'subscription_id' => $subscription->id,
                    'customer_id' => $endCustomer->id,
                    'product_id' => $subscription->product->id,
                    'start_date' => $startDate,
                    'vat_code' => $customerVatDTO->vatCode,
                    'vat_rate' => $customerVatDTO->vatRate,
                    'ledger_code' => $productGroup->ledger_code,
                    'paid' => $paid,
                    'gross_price' => $charge,
                    'net_price' => $charge,
                    'period' => $subscription->billing_period,
                    'title' => $subscription->domain ?? $subscription->product->name,
                    'description' => $description,
                    'group_label' => $subscription->domain,
                    'type' => InvoiceLine::TYPE_DEFAULT,
                    'credit_reason' => null,
                    'prepaid_reference' => $this->findPrepaidPayment($paid, $subscription),
                ]
            )
        );
    }

    /**
     * Create invoice item for specific customer and product.
     *
     * @throws InvalidCountryCodeException
     */
    public function createInvoiceForCustomer(
        Customer $customer,
        Product $product,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $grossPrice,
        ?int $netPrice = null,
        bool $paid = false,
        ?Subscription $subscription = null,
        ?int $ledgerCode = null
    ): Invoice {
        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $description = '';
        if ($subscription !== null) {
            $appendable = $subscription->domain !== null ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}" : '';
            $description = sprintf(
                '%s %s',
                $product->name,
                $appendable,
            );
        }

        $invoice = new Invoice();
        $invoice->subscription_id    = $subscription?->id;
        $invoice->customer_id        = $customer->id;
        $invoice->product_id         = $product->id;
        $invoice->ledger_code        = $ledgerCode ?? $product->productGroup->ledger_code;
        $invoice->vat_code           = $customerVatDTO->vatCode;
        $invoice->vat_rate           = $customerVatDTO->vatRate;
        $invoice->title              = $subscription->domain ?? $product->name;
        $invoice->description        = $description;
        $invoice->group_label        = $subscription?->domain;
        $invoice->type               = InvoiceLine::TYPE_DEFAULT;
        $invoice->paid               = $paid;
        $invoice->prepaid_reference  = $this->findPrepaidPayment($paid, $subscription);
        $invoice->start_date         = $startDate;
        $invoice->end_date           = $endDate;
        $invoice->period             = (int) $endDate->diffInMonths($startDate, true);
        $invoice->gross_price        = $grossPrice;
        $invoice->net_price          = $netPrice ?? $grossPrice;
        $invoice->save();

        return $invoice;
    }

    public function getLastDebitInvoiceForSubscription(Subscription $subscription): ?Invoice
    {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('gross_price', '>', 0)
            ->with('product')
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getInvoiceLineForDowngradedSubscription(Subscription $subscription): Invoice|null
    {
        $appendable = $subscription->domain !== null ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}" : '';
        $description = sprintf(
            '%s (%s) %s',
            $subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );

        return Invoice::where('subscription_id', $subscription->id)
            ->where('customer_id', $subscription->customer->id)
            ->where('description', $description)
            ->first();
    }

    public function getLatestPaidInvoiceLineForDowngradedSubscription(Subscription $subscription): Invoice|null
    {
        $timeDiff = CarbonImmutable::now()->subHour();
        $timeExclude = CarbonImmutable::create(1999);

        return Invoice::where('subscription_id', $subscription->id)
            ->where('customer_id', $subscription->customer->id)
            ->whereHas('product.productGroup', function (Builder $q): void {
                $q->where('slug', ProductGroupType::HOSTING);
            })
            ->whereNotNull('sent_to_harbor_at')
            ->where('sent_to_harbor_at', '<', $timeDiff)
            ->where('sent_to_harbor_at', '<>', $timeExclude)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function setIsSentToHarbor(int $invoiceId): int
    {
        return Invoice::query()->where('id', '=', $invoiceId)
            ->update([
                'sent_to_harbor_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * To prevent any invoices from going to Harbor twice after an error has occurred in the downgrade flow,
     * it has been decided to block the relevant Invoicelines by using the date 1-1-1999.
     * This date is reflected in selection conditions of the downgrades.
     */
    public function lockInvoiceLine(int $invoiceId): int
    {
        return Invoice::query()->where('id', '=', $invoiceId)
            ->update([
                'sent_to_harbor_at' => CarbonImmutable::create(year: 1999),
            ]);
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getNotSentToHarbor(): Collection
    {
        return Invoice::query()
            ->select('invoices.*')
            ->leftJoin('subscriptions', 'subscriptions.id', '=', 'invoices.subscription_id')
            ->whereNull('invoices.sent_to_harbor_at')
            ->where('invoices.net_price', '!=', 0)
            ->where('invoices.created_at', '>=', CarbonImmutable::now()->subMonths(6))
            ->where('invoices.created_at', '<=', CarbonImmutable::now()->subDays(3))
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->whereNotNull('subscriptions.administrative_status')
                        ->where('subscriptions.administrative_status', AdministrativeStatus::ACTIVE->value);
                })->orWhereNull('subscriptions.administrative_status');
            })->get();
    }

    /**
     * @return Builder<Invoice>
     */
    public function getUnprocessedInvoiceLinesForCustomer(Customer $customer): Builder
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNull('sent_to_harbor_at')
            ->orderByDesc('id');
    }

    /**
     * @param list<int> $invoiceLineIds
     *
     * @return Collection<int, Invoice>
     */
    public function findForCustomerByIds(Customer $customer, array $invoiceLineIds): Collection
    {
        return Invoice::query()
            ->with('customer')
            ->where('customer_id', $customer->id)
            ->whereIn('id', $invoiceLineIds)
            ->get();
    }

    public function countNotSentToHarbor(): int
    {
        return Invoice::query()
            ->whereNull('sent_to_harbor_at')
            ->where('net_price', '!=', 0)
            /* let's not mess up reporting with invoice older 2 years */
            ->where('created_at', '>=', CarbonImmutable::now()->subYears(2))
            ->count();
    }

    public function countMigratedNotSentToHarbor(): int
    {
        return Invoice::query()
            ->whereHas('subscription.migratedSubscriptions')
            ->whereNull('sent_to_harbor_at')
            ->where('net_price', '!=', 0)
            /* let's not mess up reporting with invoice older 2 years */
            ->where('created_at', '>=', CarbonImmutable::now()->subYears(2))
            ->count()
        ;
    }

    /**
     * If the invoice is (pre)paid a reference must be supplied to the external payment id (Mollie transaction id),
     * so that we can match the bank transaction later on (in Harbor) with these invoice lines and
     * the invoice can be set on the actual (hard) state paid.
     */
    public function findPrepaidPayment(bool $paid, ?Subscription $subscription): ?string
    {
        if (! $paid) {
            return null;
        }

        if ($subscription === null) {
            return null;
        }

        if ($subscription->orderLineItem === null) {
            return null;
        }

        return $subscription->orderLineItem->order->getLatestPaidPayment()?->external_id;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getNonCreditInvoiceLinesForSubscriptionAndEndDate(
        Subscription $subscription,
        CarbonImmutable $endDate,
    ): Collection {
        return Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->whereNull('parent_invoice_id')
            ->whereDate('end_date', '>', $endDate)
            ->with('subscription')
            ->get();
    }

    public function getOpenInvoiceAmount(Customer $customer): int
    {
        return intval(Invoice::where('customer_id', $customer->id)
            ->whereNull('announced_by_harbor_at')
            ->sum('net_price'));
    }
}
