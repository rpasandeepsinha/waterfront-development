<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios;

use Carbon\CarbonImmutable;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
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
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class DiscountSeeder extends Seeder
{
    public function __construct(private readonly ReferenceRepository $referenceRepo)
    {
    }

    public function run(): void
    {
        $customer = $this->customer();
        $volumeDiscount = $this->referenceRepo->get(ProductReference::VOLUME_DISCOUNT_HOSTING_BRONS_PRODUCT_DISCOUNT, ProductDiscount::class);
        $volumeDiscount->customers->add($customer);
        $volumeDiscount->save();

        $this->hostingBronze($customer);
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Discount';
        $customer->last_name = 'Kees';
        $customer->email = 'discount.kees@sandwave.io';
        $customer->organization = 'Sandwave.io';
        $customer->department = 'Lean & Synergistic Management Solutions';
        $customer->gender = Gender::MALE->value;
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->locale = Locale::DUTCH->value;
        $customer->payment_type = PaymentType::CREDIT;
        $customer->has_direct_debit = false;
        $customer->credit_limit = 5000000;
        $customer->terms_of_payment = 14;
        $customer->icp = false;
        $customer->vat_rate = 21.0;
        $customer->data_last_confirmed_at = CarbonImmutable::now();
        $customer->customer_since = CarbonImmutable::now();
        $customer->save();
        $this->referenceRepo->set(ScenarioReference::DISCOUNT_KEES, $customer);

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

    private function hostingBronze(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_BRONZE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::VOLUME_DISCOUNT_HOSTING_BRONS_PRODUCT_DISCOUNT_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $volumeDiscountProduct = $this->referenceRepo->get(ProductReference::VOLUME_DISCOUNT_BRONS, Product::class);
        $volumeDiscountPrice = $this->referenceRepo->get(ProductReference::VOLUME_DISCOUNT_BRONS_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Str::uuid()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'discounted-hosting-bronze.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Str::uuid()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->directadmin_customer_username = 'da-hosting-bronze-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Str::uuid()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Str::uuid()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $dnsPrice->contract_period;
        $dnsSubscription->billing_period = $dnsPrice->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        //Volume Discount
        $volumeDiscountOrderItem = new OrderLineItem();
        $volumeDiscountOrderItem->order_id = $order->id;
        $volumeDiscountOrderItem->domain = null;
        $volumeDiscountOrderItem->product_uuid = $volumeDiscountProduct->uuid;
        $volumeDiscountOrderItem->product_name = $volumeDiscountProduct->name;
        $volumeDiscountOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $volumeDiscountOrderItem->gross_price = $volumeDiscountPrice->price;
        $volumeDiscountOrderItem->net_price = $volumeDiscountPrice->price;
        $volumeDiscountOrderItem->billing_period = $volumeDiscountPrice->billing_period;
        $volumeDiscountOrderItem->contract_period = $volumeDiscountPrice->contract_period;
        $volumeDiscountOrderItem->should_invoice = true;
        $volumeDiscountOrderItem->save();

        $volumeDiscountSubscription = new Subscription();
        $volumeDiscountSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $volumeDiscountSubscription->uuid = Str::uuid()->toString();
        $volumeDiscountSubscription->parent_subscription_id = null;
        $volumeDiscountSubscription->customer_id = $customer->id;
        $volumeDiscountSubscription->product_uuid = $volumeDiscountProduct->uuid;
        $volumeDiscountSubscription->domain = $volumeDiscountOrderItem->domain;
        $volumeDiscountSubscription->contract_period = $volumeDiscountPrice->contract_period;
        $volumeDiscountSubscription->billing_period = $volumeDiscountPrice->billing_period;
        $volumeDiscountSubscription->net_price = $volumeDiscountOrderItem->net_price;
        $volumeDiscountSubscription->gross_price = $volumeDiscountOrderItem->gross_price;
        $volumeDiscountSubscription->technical_status = null;
        $volumeDiscountSubscription->start_date = $domainSubscription->start_date;
        $volumeDiscountSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $volumeDiscountSubscription->end_date = $domainSubscription->end_date;
        $volumeDiscountSubscription->save();

        $volumeDiscountSubscriptionPrice = new SubscriptionPrice();
        $volumeDiscountSubscriptionPrice->subscription_id = $volumeDiscountSubscription->id;
        $volumeDiscountSubscriptionPrice->valid_from = $volumeDiscountSubscription->start_date;
        $volumeDiscountSubscriptionPrice->net_price = $volumeDiscountSubscription->net_price;
        $volumeDiscountSubscriptionPrice->save();

        $volumeDiscountSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $volumeDiscountSubscriptionPriceComponent->subscription_price_id = $volumeDiscountSubscriptionPrice->id;
        $volumeDiscountSubscriptionPriceComponent->type = $volumeDiscountPrice->type;
        $volumeDiscountSubscriptionPriceComponent->percentage_discount = null;
        $volumeDiscountSubscriptionPriceComponent->fixed_discount = null;
        $volumeDiscountSubscriptionPriceComponent->fixed_price = $volumeDiscountSubscription->net_price;
        $volumeDiscountSubscriptionPriceComponent->new_price = $volumeDiscountSubscription->net_price;
        $volumeDiscountSubscriptionPriceComponent->order_applied = 1;
        $volumeDiscountSubscriptionPriceComponent->save();

        $volumeDiscountSubscription->subscription_price_id = $volumeDiscountSubscriptionPrice->id;
        $volumeDiscountSubscription->save();
    }
}
