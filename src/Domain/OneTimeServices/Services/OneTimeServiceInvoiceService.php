<?php

declare(strict_types=1);

namespace Waterfront\Domain\OneTimeServices\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Waterfront\Domain\Customers\DTO\VatDTO;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\DTO\Message\InvoiceLineMessageConfig;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Services\AdministrationFeesManager;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class OneTimeServiceInvoiceService
{
    public function __construct(
        private readonly GrossPriceResolver $grossPriceResolver,
        private readonly TranslatorInterface $translator,
        private readonly CustomerVatService $vatService,
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger,
        private readonly AdministrationFeesManager $administrationFeesManager,
    ) {
    }

    /**
     * @param Collection<int, OneTimeService> $oneTimeServices
     */
    public function createFromCollection(Collection $oneTimeServices): void
    {
        /* The collection can be multi customer, but we want to create
         * and dispatch per customer. So we regroup the given collection
         * into separate collections for each customer.
         */
        $oneTimeServicesPerCustomer = $oneTimeServices->groupBy(
            fn (OneTimeService $oneTimeService) => $oneTimeService->customer_id
        );

        foreach ($oneTimeServicesPerCustomer as $customerOneTimeServices) {
            DB::transaction(function () use ($customerOneTimeServices) {
                $customerOneTimeServices->each(
                    fn (OneTimeService $oneTimeService) => $this->createOneTimeServiceInvoices($oneTimeService)
                );
            });
            $this->sendInvoicesToHarbor($customerOneTimeServices);
        }
    }

    /**
     * This is only used for the front-end as an in place preview of how the invoice lines
     * in Harbor will be displayed on the invoice.
     *
     * @param Collection<int, OneTimeServiceContext> $contexts
     *
     * @return array<array{subscriptionId: int, domain: ?string, title: string, price: int, amount: int}>
     */
    public function getInvoiceLinesPreview(Collection $contexts): array
    {
        $invoiceLinesPreview = [];

        foreach ($contexts as $context) {
            $grossPrice = $this->grossPriceResolver->getGrossPrice($context->product, $context->subscription->product->id);
            $netPrice = $this->resolveNetPrice($grossPrice, $context->discountPercentage);
            $invoiceLinesPreview[] = [
                'subscriptionId' => $context->subscription->id,
                'domain' => $context->subscription->domain,
                'title' => $this->resolveInvoiceDescription($context->product),
                'price' => $netPrice,
                'amount' => $context->amount,
            ];
        }

        return $invoiceLinesPreview;
    }

    /**
     * @throws InvalidCountryCodeException
     *
     * @return Invoice[]
     */
    public function createOneTimeServiceInvoices(OneTimeService $oneTimeService, bool $isPaid = false): array
    {
        $subscription = $oneTimeService->subscription;
        $product = $oneTimeService->product;
        $customer = $subscription->customer;
        $customerVatDTO = $this->resolveVat($customer);
        $grossPrice = $oneTimeService->gross_price;
        $netPrice = $this->resolveNetPrice($grossPrice, $oneTimeService->discount_percentage);

        /** @var Invoice[] $createdInvoices */
        $createdInvoices = [];
        for ($i = 1; $i <= $oneTimeService->amount; $i++) {
            $createdInvoices[] = $invoice = Invoice::create([
                'subscription_id'    => $oneTimeService->subscription->id,
                'customer_id'        => $customer->id,
                'product_id'         => $product->id,
                'vat_code'           => $customerVatDTO->vatCode,
                'vat_rate'           => $customerVatDTO->vatRate,
                'ledger_code'        => $product->productGroup->ledger_code,
                'paid'               => $isPaid,
                'domain'             => $subscription->domain,
                'start_date'         => $oneTimeService->execution_date,
                'end_date'           => $oneTimeService->execution_date,
                'period'             => 0,
                'gross_price'        => $grossPrice,
                'net_price'          => $netPrice,
                'title'              => $subscription->domain ?? $product->name,
                'description'        => $this->resolveInvoiceDescription($product),
                'type'               => InvoiceLine::TYPE_DEFAULT,
                'group_label'        => $subscription->domain,
            ]);

            $oneTimeService->invoices()->attach($invoice);
        }

        $this->logger->notice(
            'Invoices created for one-time service: #{one_time_service.id}',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::META => [
                    'one_time_service.id' => $oneTimeService->id,
                ],
            ]
        );
        return $createdInvoices;
    }

    protected function resolveNetPrice(int $grossPrice, int $discountPercentage): int
    {
        return (int) round(
            $grossPrice * (1 - ($discountPercentage * 0.01))
        );
    }

    /**
     * @param Collection<int, OneTimeService> $oneTimeServices
     */
    private function sendInvoicesToHarbor(Collection $oneTimeServices): void
    {
        $invoicesToDispatch = new Collection();
        $invoiceConfigs = new Collection();
        $customer = null;
        foreach ($oneTimeServices as $oneTimeService) {
            $customer = $oneTimeService->customer;
            $oneTimeServiceProduct = $oneTimeService->product;
            $invoices = $oneTimeService->invoices()->get();
            $invoicesToDispatch->push(...$invoices);
            $invoiceConfigs->push(...$invoices->map(
                function (Invoice $invoice) use ($oneTimeServiceProduct): InvoiceLineMessageConfig {
                    $subscription = $invoice->subscription;
                    Assert::isInstanceOf($subscription, Subscription::class);

                    return new InvoiceLineMessageConfig(
                        invoice: $invoice,
                        product: $oneTimeServiceProduct,
                        subscription: $subscription,
                    );
                }
            ));
        }

        Assert::notNull($customer);

        if ($invoicesToDispatch->isEmpty()) {
            $this->logger->critical(sprintf(
                'Invoice one-time services action resulted in zero invoices to dispatch: %s',
                $oneTimeServices->implode(fn (OneTimeService $oneTimeService) => $oneTimeService->id)
            ));
            return;
        }

        if ($this->administrationFeesManager->shouldBeChargedWithOneTimeService($customer)) {
            $administrationFees = $this->administrationFeesManager->getAdministrationFees($customer);

            if ($administrationFees !== null) {
                $invoicesToDispatch->push(
                    $this->administrationFeesManager->createAdministrationFeesInvoice(
                        customer: $customer,
                        administrationFees: $administrationFees,
                        dispatchInvoiceCreated: false
                    )
                );
            }
        }

        $this->messageService->queue($customer, $invoicesToDispatch->all(), $invoiceConfigs->all(), true);
    }

    /**
     *
     * @throws InvalidCountryCodeException
     *
     */
    private function resolveVat(Customer $customer): VatDTO
    {
        static $vatCache = [];

        if (! array_key_exists($customer->id, $vatCache)) {
            $customerVatDTO = $this->vatService->getCustomerVatData($customer);
            return $vatCache[$customer->id] = $customerVatDTO;
        }

        return $vatCache[$customer->id];
    }

    private function resolveInvoiceDescription(Product $oneTimeServiceProduct): string
    {
        return sprintf(
            '%s: %s',
            $this->translator->translate('invoice.one_time_service.description_prefix'),
            $oneTimeServiceProduct->name,
        );
    }
}
