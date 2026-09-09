<?php

declare(strict_types=1);

namespace Waterfront\Domain\Invoices\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\ItemNotFoundException;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Invoices\DTO\AdministrationFees;
use Waterfront\Domain\Invoices\Events\InvoiceCreatedEvent;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\RegistrationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class AdministrationFeesManager
{
    private readonly bool $administrationFeesDailyBillingEnabled;

    private readonly bool $administrationFeesOrderBillingEnabled;

    private readonly bool $administrationFeesOtsEnabled;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly CustomerVatService $vatService,
        private readonly Dispatcher $eventDispatcher,
        ConfigurationInterface $configuration,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
        private readonly PriceResolver $priceResolver,
    ) {
        $this->administrationFeesDailyBillingEnabled = $configuration->getAsBoolean('financial.administration_fees_daily_billing_enabled');
        $this->administrationFeesOrderBillingEnabled = $configuration->getAsBoolean('financial.administration_fees_order_billing_enabled');
        $this->administrationFeesOtsEnabled = $configuration->getAsBoolean('financial.administration_fees_ots_enabled');
    }

    public function createAdministrationFeesInvoice(
        Customer $customer,
        AdministrationFees $administrationFees,
        ?int $administrationFeesPrice = null,
        ?string $prepaidReference = null,
        bool $dispatchInvoiceCreated = true,
    ): Invoice {
        $administrationFeesPrice ??= $administrationFees->price;

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $invoice = new Invoice();
        $invoice->subscription_id = null;
        $invoice->customer_id = $customer->id;
        $invoice->product_id = $administrationFees->productId;
        $invoice->vat_code = $customerVatDTO->vatCode;
        $invoice->vat_rate = $customerVatDTO->vatRate;
        $invoice->title = $administrationFees->name;
        $invoice->description = $this->translator->translate('invoice.administration-fees.description');
        $invoice->group_label = $administrationFees->name;
        $invoice->type = InvoiceLine::TYPE_DEFAULT;
        $invoice->paid = $prepaidReference !== null;
        $invoice->prepaid_reference = $prepaidReference;
        $invoice->start_date = CarbonImmutable::now();
        $invoice->end_date = CarbonImmutable::now();
        $invoice->period = 0;
        $invoice->gross_price = $administrationFeesPrice;
        $invoice->net_price = $administrationFeesPrice;

        $invoice->save();

        if ($dispatchInvoiceCreated) {
            $this->eventDispatcher->dispatch(new InvoiceCreatedEvent($invoice, false));
        }

        return $invoice;
    }

    public function shouldBeChargedWithOrder(
        Customer $customer,
        ?string $paymentMethod,
        bool $createDirectDebitMandateWithOrder
    ): bool {
        return $this->administrationFeesOrderBillingEnabled
            && $customer->has_direct_debit === false
            && $paymentMethod !== null
            && $paymentMethod !== PaymentType::CREDIT->value
            && $createDirectDebitMandateWithOrder === false;
    }

    public function shouldBeChargedWithDailyBilling(Customer $customer): bool
    {
        return $this->administrationFeesDailyBillingEnabled
            && $customer->has_direct_debit === false;
    }

    public function shouldBeChargedWithOneTimeService(Customer $customer): bool
    {
        return $this->administrationFeesOtsEnabled
            && $customer->has_direct_debit === false;
    }

    public function getAdministrationFees(Customer $customer): ?AdministrationFees
    {
        if (! $this->administrationFeesDailyBillingEnabled
            && ! $this->administrationFeesOrderBillingEnabled
            && ! $this->administrationFeesOtsEnabled) {
            return null;
        }

        try {
            $product = $this->productRepository->findProductBySlug(ProductType::ADMINISTRATION_FEES->value);
            $priceRequest = new PriceRequest([new RegistrationPriceRequest($product)], $customer);
            $priceList = $this->priceResolver->getPriceList($priceRequest);
            $price = $priceList->getProductPrice($product->slug, 1, 1);
        } catch (ItemNotFoundException|ModelNotFoundException $exception) {
            $this->logger->warning('Administration fees could not be loaded: ' . $exception->getMessage(), [
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
            return null;
        }

        if ($price->calculatedPrice <= 0) {
            return null;
        }

        return new AdministrationFees(
            $product->id,
            $price->calculatedPrice,
            $product->name,
        );
    }
}
