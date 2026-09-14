<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios;

use Carbon\CarbonImmutable;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Models\Transfer;
use Webmozart\Assert\Assert;

class TransferSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $customerSender = $this->customerSender();
        $this->contactsSender($customerSender);
        $this->domainContactSender($customerSender);

        $customerReceiver = $this->customerReceiver();
        $this->contactsReceiver($customerReceiver);
        $this->domainContactReceiver($customerReceiver);

        $subscriptionTransferRequested = $this->domainNl($customerSender, 'domain-transfer-requested.nl');
        $this->transferRequested($customerSender, $customerReceiver, $subscriptionTransferRequested);

        $subscriptionTransferAccepted = $this->domainNl($customerSender, 'domain-transfer-accepted.nl');
        $this->transferAccepted($customerSender, $customerReceiver, $subscriptionTransferAccepted);

        $subscriptionTransferStarted = $this->domainNl($customerSender, 'domain-transfer-started.nl');
        $this->transferStarted($customerSender, $customerReceiver, $subscriptionTransferStarted);

        $subscriptionTransferFailed = $this->domainNl($customerSender, 'domain-transfer-failed.nl');
        $this->transferFailed($customerSender, $customerReceiver, $subscriptionTransferFailed);

        $subscriptionTransferRejected = $this->domainNl($customerSender, 'domain-transfer-rejected.nl');
        $this->transferRejected($customerSender, $customerReceiver, $subscriptionTransferRejected);

        $subscriptionTransferCompleted = $this->domainNl($customerSender, 'domain-transfer-completed.nl');
        $this->transferCompleted($customerSender, $customerReceiver, $subscriptionTransferCompleted);

        $subscriptionTransferCompletedButFails = $this->domainNl($customerSender, 'domain-transfer-completed-2.nl');
        $this->transferCompleteButFailedTransfer(
            $customerSender,
            $customerReceiver,
            $subscriptionTransferCompleted,
            $subscriptionTransferCompletedButFails,
        );
    }

    private function customerSender(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Test Transfer';
        $customer->last_name = 'Kees Sender';
        $customer->email = 'test.kees.transfer.sender@sandwave.io';
        $customer->organization = 'Sandwave.io Transfer Sender';
        $customer->department = 'Lean & Synergistic Management Solutions';
        $customer->gender = Gender::MALE->value;
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->locale = Locale::DUTCH->value;
        $customer->payment_type = PaymentType::CREDIT;
        $customer->has_direct_debit = true;
        $customer->credit_limit = 5000000;
        $customer->terms_of_payment = 14;
        $customer->icp = false;
        $customer->vat_rate = 21.0;
        $customer->data_last_confirmed_at = CarbonImmutable::now();
        $customer->customer_since = CarbonImmutable::now();
        $customer->save();

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->save();

        return $customer;
    }

    private function customerReceiver(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Test Transfer';
        $customer->last_name = 'Receiver Kees';
        $customer->email = 'test.kees.transfer.receiver@sandwave.io';
        $customer->organization = 'Sandwave.io Transfer Receiver';
        $customer->department = 'Lean & Synergistic Management Solutions';
        $customer->gender = Gender::MALE->value;
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->locale = Locale::DUTCH->value;
        $customer->payment_type = PaymentType::CREDIT;
        $customer->has_direct_debit = true;
        $customer->credit_limit = 5000000;
        $customer->terms_of_payment = 14;
        $customer->icp = false;
        $customer->vat_rate = 21.0;
        $customer->data_last_confirmed_at = CarbonImmutable::now();
        $customer->customer_since = CarbonImmutable::now();
        $customer->save();

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->save();

        return $customer;
    }

    private function contactsSender(Customer $customer): void
    {
        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'Kees';
        $contact->email = 'test.kees.transfer.sender@sandwave.io';
        $contact->type = CustomerContactType::DEFAULT->value;
        $contact->save();
    }

    private function contactsReceiver(Customer $customer): void
    {
        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'Kees';
        $contact->email = 'test.kees.transfer.receiver@sandwave.io';
        $contact->type = CustomerContactType::DEFAULT->value;
        $contact->save();
    }

    private function domainContactSender(Customer $customer): void
    {
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);

        $contact = new DomainContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->email = 'fake@faker.nl';
        $contact->first_name = 'First';
        $contact->last_name = 'Last';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '450647';
        $contact->street_name = 'Fakestreet';
        $contact->street_number = '1';
        $contact->zip_code = '1234 AB';
        $contact->city = 'Fakecity';
        $contact->country_code = 'NL';
        $contact->organization = 'Sandwave';
        $contact->default_owner = true;
        $contact->customer_id = $customer->id;
        $contact->save();

        $contact->providers()->attach($provider, [
            'external_contact' => 123,
        ]);
    }

    private function domainContactReceiver(Customer $customer): void
    {
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);

        $contact = new DomainContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->email = 'fake@faker.nl';
        $contact->first_name = 'First';
        $contact->last_name = 'Last';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '450647';
        $contact->street_name = 'Fakestreet';
        $contact->street_number = '1';
        $contact->zip_code = '1234 AB';
        $contact->city = 'Fakecity';
        $contact->country_code = 'NL';
        $contact->organization = 'Sandwave';
        $contact->default_owner = true;
        $contact->customer_id = $customer->id;
        $contact->save();

        $contact->providers()->attach($provider, [
            'external_contact' => 456,
        ]);
    }

    private function domainNl(Customer $customer, string $domainName): Subscription
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(
            ProductReference::DOMAIN_NL_REGISTRATION_PRICE,
            ProductPriceComponent::class,
        );
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(
            ProductReference::DNS_FREE_REGISTRATION_PRICE,
            ProductPriceComponent::class,
        );

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $domainName;
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = $this->createSubscription($customer, $product, $price, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $provider, $domainContact);

        $dnsOrderItem = $this->createDnsOrderItem($order, $domainOrderItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem,
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);

        return $domainSubscription;
    }

    private function createDomainDeployment(
        Subscription $domainSubscription,
        Provider $domainProvider,
        DomainContact $domainContact,
    ): void {
        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();
    }

    private function createDnsDeploymentWithNameservers(Subscription $dnsSubscription): void
    {
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment
            ->dnsNameservers()
            ->saveMany([
                $dnsNameserver1,
                $dnsNameserver2,
                $dnsNameserver3,
            ]);
    }

    private function createSubscription(
        Customer $customer,
        Product $product,
        ProductPriceComponent $productPrice,
        OrderLineItem $orderItem,
    ): Subscription {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = $orderItem->domain;
        $subscription->contract_period = $productPrice->contract_period;
        $subscription->billing_period = $productPrice->billing_period;
        Assert::greaterThanEq($orderItem->net_price, 0);
        $subscription->net_price = $orderItem->net_price;
        Assert::greaterThanEq($orderItem->gross_price, 0);
        $subscription->gross_price = $orderItem->gross_price;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths(
            $subscription->billing_period,
        );
        $subscription->end_date = $subscription->start_date->addMonths(
            $subscription->contract_period,
        );
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $productPrice->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        return $subscription;
    }

    private function createInvoiceItem(
        Subscription $subscription,
        Customer $customer,
        Product $product,
    ): Invoice {
        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $subscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $subscription->start_date;
        $invoiceItem->end_date = $subscription->next_billing_date;
        $invoiceItem->period = $subscription->contract_period;
        $invoiceItem->net_price = $subscription->net_price;
        $invoiceItem->gross_price = $subscription->gross_price;
        $invoiceItem->title = $subscription->domain ?? $subscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $subscription->product->name, $subscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        return $invoiceItem;
    }

    private function createDnsSubscription(
        Subscription $domainSubscription,
        Customer $customer,
        Product $dnsProduct,
        ProductPriceComponent $dnsPrice,
        OrderLineItem $dnsOrderItem,
    ): Subscription {
        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Str::uuid()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $dnsPrice->contract_period;
        $dnsSubscription->billing_period = $dnsPrice->billing_period;

        Assert::greaterThanEq($dnsOrderItem->net_price, 0);
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        Assert::greaterThanEq($dnsOrderItem->gross_price, 0);
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
        $dnsSubscription->save();

        $dnsSubscriptionPrice = new SubscriptionPrice();
        $dnsSubscriptionPrice->subscription_id = $dnsSubscription->id;
        $dnsSubscriptionPrice->valid_from = $dnsSubscription->start_date;
        $dnsSubscriptionPrice->net_price = $dnsSubscription->net_price;
        $dnsSubscriptionPrice->save();

        $dnsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $dnsSubscriptionPriceComponent->subscription_price_id = $dnsSubscriptionPrice->id;
        $dnsSubscriptionPriceComponent->type = $dnsPrice->type;
        $dnsSubscriptionPriceComponent->percentage_discount = null;
        $dnsSubscriptionPriceComponent->fixed_discount = null;
        $dnsSubscriptionPriceComponent->fixed_price = $dnsSubscription->net_price;
        $dnsSubscriptionPriceComponent->new_price = $dnsSubscription->net_price;
        $dnsSubscriptionPriceComponent->order_applied = 1;
        $dnsSubscriptionPriceComponent->save();

        $dnsSubscription->subscription_price_id = $dnsSubscriptionPrice->id;
        $dnsSubscription->save();

        return $dnsSubscription;
    }

    private function createDnsOrderItem(
        Order $order,
        OrderLineItem $orderlineItem,
        Product $dnsProduct,
        ProductPriceComponent $dnsPrice,
    ): OrderLineItem {
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $orderlineItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        return $dnsOrderItem;
    }

    private function transferRequested(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerSender);
        $transfer->toCustomer()->associate($customerReceiver);
        $transfer->save();
        $transfer->subscriptions()->save($subscription);
    }

    private function transferAccepted(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerSender);
        $transfer->toCustomer()->associate($customerReceiver);
        $transfer->save();
        $transfer->subscriptions()->save($subscription);

        $transfer->accept();
    }

    private function transferStarted(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerSender);
        $transfer->toCustomer()->associate($customerReceiver);
        $transfer->save();
        $transfer->subscriptions()->save($subscription);

        $transfer->accept();
        $transfer->start();
    }

    private function transferCompleted(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        // The receiver and sender are swapped intentionally here to reflect a completed transfer.
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerReceiver);
        $transfer->toCustomer()->associate($customerSender);
        $transfer->save();
        $transfer->subscriptions()->save($subscription);

        $transfer->accept();
        $transfer->start();
        $transfer->complete();
    }

    private function transferCompleteButFailedTransfer(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
        Subscription $subscriptionToFail,
    ): void {
        // The receiver and sender are swapped intentionally here to reflect a completed transfer.
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerReceiver);
        $transfer->toCustomer()->associate($customerSender);
        $transfer->save();
        $transfer->subscriptions()->attach($subscription, ['executed_at' => CarbonImmutable::now()]);
        $transfer->subscriptions()->attach(
            $subscriptionToFail->id,
            [
                'failed_at' => CarbonImmutable::now(),
                'reason_failed' => sprintf(
                    '[EXECUTE TRANSFER] Not allowed to start transfer with ID: {%s} REASON: failed on purpose because this is a seeder',
                    $transfer->id,
                ),
            ],
        );

        $transfer->accept();
        $transfer->start();
        $transfer->complete();
    }

    private function transferFailed(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerSender);
        $transfer->toCustomer()->associate($customerReceiver);
        $transfer->save();

        $transfer->subscriptions()->attach(
            $subscription->id,
            [
                'failed_at' => CarbonImmutable::now(),
                'reason_failed' => sprintf(
                    '[EXECUTE TRANSFER] Not allowed to start transfer with ID: {%s} REASON: failed on purpose because this is a seeder',
                    $transfer->id,
                ),
            ],
        );
    }

    private function transferRejected(
        Customer $customerSender,
        Customer $customerReceiver,
        Subscription $subscription,
    ): void {
        $transfer = new Transfer();
        $transfer->uuid = Uuid::uuid4()->toString();
        $transfer->fromCustomer()->associate($customerSender);
        $transfer->toCustomer()->associate($customerReceiver);
        $transfer->save();
        $transfer->subscriptions()->save($subscription);

        $transfer->reject();
    }
}
