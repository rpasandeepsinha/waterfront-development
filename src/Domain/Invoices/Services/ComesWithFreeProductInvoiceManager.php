<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

readonly class ComesWithFreeProductInvoiceManager
{
    public function __construct(
        private CustomerVatService $vatService,
        private Dispatcher $eventDispatcher,
        private TranslatorInterface $translator,
        private ProductRepository $productRepository,
        private PriceResolver $priceResolver,
    ) {
    }

    public function isSubscriptionWhichComesWithFreeProduct(Subscription $subscription): bool
    {
        $comesWithFreeProduct = $this->productRepository->comesWithFreeProduct($subscription->product);

        return $comesWithFreeProduct instanceof Product;
    }

    public function createInvoice(
        Subscription $subscription,
        Invoice $paidInvoice,
        ?string $prepaidReference = null,
        bool $dispatchInvoiceCreated = true,
        ProductPriceType $priceType = ProductPriceType::REGISTRATION,
    ): ?Invoice {
        $comesWithFreeProduct = $this->productRepository->comesWithFreeProduct($subscription->product);

        if ($comesWithFreeProduct === null) {
            return null;
        }

        $productPriceRequest = match ($priceType) {
            ProductPriceType::REGISTRATION => new RegistrationPriceRequest($comesWithFreeProduct),
            ProductPriceType::PROLONGATION => new ProlongationPriceRequest($comesWithFreeProduct),
        };
        $priceList = $this->priceResolver->getPriceList(
            new PriceRequest([$productPriceRequest], $subscription->customer),
        );
        $price = $priceList->getProductPrice(
            $comesWithFreeProduct->slug,
            $subscription->contract_period,
            $subscription->billing_period,
        );

        return $this->createInvoiceForCustomer(
            customer: $subscription->customer,
            product: $comesWithFreeProduct,
            startDate: $paidInvoice->start_date,
            endDate: $paidInvoice->end_date,
            grossPrice: $price->regularPrice,
            netPrice: 0,
            prepaidReference: $prepaidReference,
            subscription: $subscription,
            dispatchInvoiceCreated: $dispatchInvoiceCreated,
        );
    }

    /**
     * Create invoice item for specific customer and product.
     *
     * @throws InvalidCountryCodeException
     */
    private function createInvoiceForCustomer(
        Customer $customer,
        Product $product,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $grossPrice,
        ?int $netPrice = null,
        ?string $prepaidReference = null,
        ?Subscription $subscription = null,
        bool $dispatchInvoiceCreated = true,
    ): Invoice {
        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $description = '';
        if ($subscription !== null) {
            $appendable = $subscription->domain !== null
                ? $this->translator->translate('invoice.description.for') . " {$subscription->domain}"
                : '';
            $description = sprintf(
                '%s %s',
                $product->name,
                $appendable,
            );
        }

        $invoice = new Invoice();
        $invoice->subscription_id = $subscription?->id;
        $invoice->customer_id = $customer->id;
        $invoice->product_id = $product->id;
        $invoice->ledger_code = $product->productGroup->ledger_code;
        $invoice->vat_code = $customerVatDTO->vatCode;
        $invoice->vat_rate = $customerVatDTO->vatRate;
        $invoice->title = $product->name;
        $invoice->description = $description;
        $invoice->group_label = $subscription?->domain;
        $invoice->type = InvoiceLine::TYPE_DEFAULT;
        $invoice->paid = $prepaidReference !== null;
        $invoice->prepaid_reference = $prepaidReference;
        $invoice->start_date = $startDate;
        $invoice->end_date = $endDate;
        $invoice->period = (int) $endDate->diffInMonths($startDate, true);
        $invoice->gross_price = $grossPrice;
        $invoice->net_price = $netPrice ?? $grossPrice;
        $invoice->save();

        if ($dispatchInvoiceCreated) {
            $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($invoice, false));
        }

        return $invoice;
    }
}
