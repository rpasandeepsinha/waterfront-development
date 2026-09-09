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
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplate;
use Waterfront\Domain\Ferry\Models\MigratedDnsTemplateRecord;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class MigrationsSeeder extends Seeder
{
    public function __construct(private readonly ReferenceRepository $referenceRepository)
    {
    }

    public function run(): void
    {
        $customer = $this->customer();
        $migratedCustomer = $this->migratedCustomer($customer);

        $this->dnsTemplates($customer, $migratedCustomer);
        $this->ferryInternalNameservers();
        $this->wallet($customer);

        $this->domainSubscriptionPlaceholder($customer, $migratedCustomer);
        $this->domainSubscriptionMigrated($customer, $migratedCustomer);
        $this->hostingSubscriptionPlaceholder($customer, $migratedCustomer);
        $this->hostingSubscriptionMigrated($customer, $migratedCustomer);
        $this->mailOnlySubscription($customer, $migratedCustomer);
        $this->backupSubscription($customer, $migratedCustomer);
        $this->sslSubscription($customer, $migratedCustomer);
        $this->redirectSubscription($customer, $migratedCustomer);
        $this->sitebuilderSubscription($customer, $migratedCustomer);
        $this->sitebuilderNoDeploymentOrPlaceholderSubscription($customer, $migratedCustomer);
        $this->resellerHostingSubscription($customer, $migratedCustomer);
        $this->backupSubscription($customer, $migratedCustomer);
    }

    public function migratedCustomer(Customer $customer): MigratedCustomer
    {
        $migratedCustomer = new MigratedCustomer();
        $migratedCustomer->reference_customer_number = '1234';
        $migratedCustomer->reference_name = 'Versio';
        $migratedCustomer->group_type = 'batch_25';
        $migratedCustomer->successful = false;
        $migratedCustomer->save();
        $migratedCustomer->customers()->attach($customer);
        return $migratedCustomer;
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Migration';
        $customer->last_name = 'Kees';
        $customer->email = 'migration.kees@sandwave.io';
        $customer->organization = 'Sandwave.io';
        $customer->department = null;
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

        $this->referenceRepository->set(ScenarioReference::MIGRATION_CUSTOMER, $customer);

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->type = 'default';
        $address->save();

        $this->testKeesFerryContacts($customer);
        return $customer;
    }

    private function testKeesFerryContacts(Customer $customer): void
    {
        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Migration';
        $contact->last_name = 'Kees';
        $contact->company = 'Sandwave Existing Business';
        $contact->email = 'test.kees@sandwave.io';
        $contact->type = CustomerContactType::DEFAULT->value;
        $contact->save();

        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Another Contact';
        $contact->last_name = 'Financial last name';
        $contact->company = null;
        $contact->email = 'test.kees@sandwave.io';
        $contact->type = CustomerContactType::FINANCIAL->value;
        $contact->save();
    }

    private function domainSubscriptionPlaceholder(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product = $this->referenceRepository->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepository->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::DOMAIN_PROVIDER_PLACEHOLDER, Provider::class);

        $dnsNameserver1 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-placeholder-domain.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new DomainDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->last_result = null;
        $deployment->last_result_received = null;
        $deployment->dnssec_enabled = false;
        $deployment->private_whois_enabled = false;
        $deployment->contact_owner_id = null;
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '12';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_domain_placeholder';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);

        $dnsProduct = $this->referenceRepository->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepository->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Str::uuid()->toString();
        $dnsSubscription->customer_id = $ferryTestKeesCustomer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = 'ferry-migration-placeholder-domain.test';
        $dnsSubscription->contract_period = $dnsPrice->contract_period;
        $dnsSubscription->billing_period = $dnsPrice->billing_period;
        $dnsSubscription->net_price = 0;
        $dnsSubscription->gross_price = 0;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = CarbonImmutable::yesterday();
        $dnsSubscription->next_billing_date = $dnsSubscription->start_date->addMonths($dnsSubscription->billing_period);
        $dnsSubscription->end_date = $dnsSubscription->start_date->addMonths($dnsSubscription->contract_period);
        $dnsSubscription->parent()->associate($subscription);
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
    }

    private function domainSubscriptionMigrated(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product = $this->referenceRepository->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepository->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::DOMAIN_PROVIDER_RTR, Provider::class);

        $dnsNameserver1 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-migrated-domain.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = $price->price;
        $subscription->gross_price = $price->price;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $contact = new DomainContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->email = 'migration.kees@sandwave.io';
        $contact->first_name = 'Migration';
        $contact->last_name = 'Kees';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '450647';
        $contact->street_name = 'Migrationstreet';
        $contact->street_number = '1';
        $contact->zip_code = '1234 AB';
        $contact->city = 'Migrationcity';
        $contact->country_code = 'NL';
        $contact->organization = 'Sandwave';
        $contact->default_owner = true;
        $contact->customer_id = $ferryTestKeesCustomer->id;
        $contact->save();

        $contact->providers()->attach($provider, [
            'external_contact' => 'ferry-contact-123',
        ]);

        $deployment = new DomainDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->last_result = null;
        $deployment->last_result_received = null;
        $deployment->dnssec_enabled = false;
        $deployment->private_whois_enabled = false;
        $deployment->contactOwner()->associate($contact);
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '12';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_domain_migrated';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);

        $dnsProduct = $this->referenceRepository->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepository->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Str::uuid()->toString();
        $dnsSubscription->customer_id = $ferryTestKeesCustomer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = 'ferry-migration-migrated-domain.test';
        $dnsSubscription->contract_period = $dnsPrice->contract_period;
        $dnsSubscription->billing_period = $dnsPrice->billing_period;
        $dnsSubscription->net_price = 0;
        $dnsSubscription->gross_price = 0;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = CarbonImmutable::yesterday();
        $dnsSubscription->next_billing_date = $dnsSubscription->start_date->addMonths($dnsSubscription->billing_period);
        $dnsSubscription->end_date = $dnsSubscription->start_date->addMonths($dnsSubscription->contract_period);
        $dnsSubscription->parent()->associate($subscription);
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
    }

    private function hostingSubscriptionPlaceholder(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::HOSTING_BRONZE, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::HOSTING_BRONZE_PROLONGATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::HOSTING_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-placeholder-hosting.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::from($price->type->value);
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new HostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->server_id = null;
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '13';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_hosting_placeholder';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function hostingSubscriptionMigrated(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::HOSTING_BRONZE, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::HOSTING_BRONZE_PROLONGATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::HOSTING_PROVIDER_PLESK, Provider::class);
        $server   = $this->referenceRepository->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-migrated-hosting.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = $price->price;
        $subscription->gross_price = $price->price;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::from($price->type->value);
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new HostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->plesk_customer_username = 'ferry-plesk-username';
        $deployment->server()->associate($server);
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '13';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_hosting_migrated';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function mailOnlySubscription(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_PROLONGATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-placeholder-mail-only.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::from($price->type->value);
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new HostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = null;
        $deployment->mail_only_provider_id = $provider->id;
        $deployment->saveQuietly(); // Has provider setting logic in the boot

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '14';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_mail_only';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function sslSubscription(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::SSL_SINGLE_DOMAIN, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::SSL_SINGLE_DOMAIN_PROLONGATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepository->get(ProductReference::SSL_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-placeholder-ssl.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::from($price->type->value);
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new SslDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->expire_date = CarbonImmutable::now()->addMonths(6);
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '15';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_ssl';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function redirectSubscription(Customer $customer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::REDIRECT_PAID_REDIRECT, Product::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-redirect.test';
        $subscription->contract_period = 12;
        $subscription->billing_period = 12;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = PriceComponentType::REGISTRATION;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '16';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_redirect';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function sitebuilderNoDeploymentOrPlaceholderSubscription(Customer $customer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);
        $mailProvider        = $this->referenceRepository->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-sitebuilder-no-deployment-or-placeholder.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new HostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = null;
        $deployment->mail_only_provider_id = $mailProvider->id;
        $deployment->saveQuietly(); // Has provider setting logic in the boot

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '16';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_sitebuilder_no_deployment';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function sitebuilderSubscription(Customer $customer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $sitebuilderProvider = $this->referenceRepository->get(ProductReference::SITEBUILDER_PROVIDER_PLACEHOLDER, Provider::class);
        $mailProvider        = $this->referenceRepository->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-sitebuilder.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new HostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = null;
        $deployment->mail_only_provider_id = $mailProvider->id;
        $deployment->sitebuilder_provider_id = $sitebuilderProvider->id;
        $deployment->saveQuietly(); // Has provider setting logic in the boot

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '16';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_sitebuilder';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function dnsTemplates(Customer $customer, MigratedCustomer $migratedCustomer): void
    {
        $template = new DnsCustomerTemplate();
        $template->name = 'Migrated DNS template';
        $template->customer_id = $customer->id;
        $template->save();

        $record1 = new DnsCustomerTemplateRecord();
        $record1->name = '@';
        $record1->content = '::1';
        $record1->type = DnsRecordType::AAAA->value;
        $record1->ttl = 3600;
        $record1->template_id = $template->id;
        $record1->save();

        $record2 = new DnsCustomerTemplateRecord();
        $record2->name = 'subdomain.@';
        $record2->content = 'test.@';
        $record2->type = DnsRecordType::TXT->value;
        $record2->ttl = 3600;
        $record2->template_id = $template->id;
        $record2->save();

        $migratedTemplate = new MigratedDnsTemplate();
        $migratedTemplate->migrated_customer_id = $migratedCustomer->id;
        $migratedTemplate->dns_customer_template_id = $template->id;
        $migratedTemplate->reference_template_id = 'test_dns_template';
        $migratedTemplate->save();

        $migratedRecord = new MigratedDnsTemplateRecord();
        $migratedRecord->migrated_dns_template_id = $migratedTemplate->id;
        $migratedRecord->dns_customer_template_record_id = $record1->id;
        $migratedRecord->reference_record_id = 'migrated_template_record_1';
        $migratedTemplate->save();

        $migratedRecord = new MigratedDnsTemplateRecord();
        $migratedRecord->migrated_dns_template_id = $migratedTemplate->id;
        $migratedRecord->dns_customer_template_record_id = $record2->id;
        $migratedRecord->reference_record_id = 'migrated_template_record_2';
        $migratedRecord->save();
    }

    private function resellerHostingSubscription(Customer $ferryTestKeesCustomer, MigratedCustomer $migratedCustomer): void
    {
        $product  = $this->referenceRepository->get(ProductReference::RESELLER_HOSTING_BRONS, Product::class);
        $price    = $this->referenceRepository->get(ProductReference::RESELLER_HOSTING_BRONS_PROLONGATION_PRICE, ProductPriceComponent::class);

        $provider = $this->referenceRepository->get(ProductReference::HOSTING_PROVIDER_PLACEHOLDER, Provider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $ferryTestKeesCustomer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-reseller-hosting.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new ResellerHostingDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->server_id = null;
        $deployment->saveQuietly();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '17';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_reseller_hosting';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
    }

    private function backupSubscription(Customer $customer, MigratedCustomer $migratedCustomer): void
    {
        $product = $this->referenceRepository->get(ProductReference::BACKUP_ACRONIS_50, Product::class);
        $price = $this->referenceRepository->get(ProductReference::BACKUP_ACRONIS_50_REGISTRATION_PRICE, ProductPriceComponent::class);

        $acronisProvider = $this->referenceRepository->get(ProductReference::ACRONIS_PROVIDER_YOURHOSTING, AcronisProvider::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-migration-backup-placeholder.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '1337';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_backup_placeholder';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);

        $subscriptionMigrated = new Subscription();
        $subscriptionMigrated->uuid = Str::uuid()->toString();
        $subscriptionMigrated->customer_id = $customer->id;
        $subscriptionMigrated->product_uuid = $product->uuid;
        $subscriptionMigrated->domain = 'ferry-migration-backup.test';
        $subscriptionMigrated->contract_period = $price->contract_period;
        $subscriptionMigrated->billing_period = $price->billing_period;
        $subscriptionMigrated->net_price = 0;
        $subscriptionMigrated->gross_price = 0;
        $subscriptionMigrated->technical_status = TechnicalStatus::OK->value;
        $subscriptionMigrated->start_date = CarbonImmutable::yesterday();
        $subscriptionMigrated->next_billing_date = $subscriptionMigrated->start_date->addMonths($subscriptionMigrated->billing_period);
        $subscriptionMigrated->end_date = $subscriptionMigrated->start_date->addMonths($subscriptionMigrated->contract_period);
        $subscriptionMigrated->save();

        $subscriptionMigratedPrice = new SubscriptionPrice();
        $subscriptionMigratedPrice->subscription_id = $subscriptionMigrated->id;
        $subscriptionMigratedPrice->valid_from = $subscriptionMigrated->start_date;
        $subscriptionMigratedPrice->net_price = $subscriptionMigrated->net_price;
        $subscriptionMigratedPrice->save();

        $subscriptionMigratedPriceComponent = new SubscriptionPriceComponent();
        $subscriptionMigratedPriceComponent->subscription_price_id = $subscriptionMigratedPrice->id;
        $subscriptionMigratedPriceComponent->type = $price->type;
        $subscriptionMigratedPriceComponent->percentage_discount = null;
        $subscriptionMigratedPriceComponent->fixed_discount = null;
        $subscriptionMigratedPriceComponent->fixed_price = $subscriptionMigrated->net_price;
        $subscriptionMigratedPriceComponent->new_price = $subscriptionMigrated->net_price;
        $subscriptionMigratedPriceComponent->order_applied = 1;
        $subscriptionMigratedPriceComponent->save();

        $subscriptionMigrated->subscription_price_id = $subscriptionMigratedPrice->id;
        $subscriptionMigrated->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_subscription_id = 'existing_business_product_backup';
        $migratedSubscription->reference_product_id = 'product_small';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscriptionMigrated);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);
        $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($subscriptionMigrated->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($subscriptionMigrated->uuid);
        $provisionRequest->request_data = (string) json_encode(['language' => 'en', 'email' => 'test.kees-not-valid', 'firstname' => 'Test', 'username' => 'tkees', 'cloudStorageInGb' => 50.0]);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_BACKUP_DEPLOYMENTS_FROM_MIGRATION;
        $provisionRequest->request_type = ProvisionType::BACKUP;
        $provisionRequest->provision_provider = ProvisionProvider::ACRONIS;
        $provisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $provisionRequest->id;
        $provisionResult->response = (string) json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'acronis backup successfully provisioned']);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $provisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $provisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $backupDeployment = new BackupDeployment();
        $backupDeployment->uuid = Uuid::uuid4();
        $backupDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $backupDeployment->save();

        $acronisBackupDeployment = new AcronisBackupDeployment();
        $acronisBackupDeployment->uuid = Uuid::uuid4();
        $acronisBackupDeployment->backup_deployment_id = $backupDeployment->id;
        $acronisBackupDeployment->acronis_provider_id = $acronisProvider->id;

        $acronisBackupDeployment->tenant_uuid = Uuid::uuid4();
        $acronisBackupDeployment->user_uuid = Uuid::uuid4();
        $acronisBackupDeployment->save();
    }

    private function ferryInternalNameservers(): void
    {
        $nameserver1 = new FerryInternalNameserver();
        $nameserver2 = new FerryInternalNameserver();
        $nameserver3 = new FerryInternalNameserver();

        $nameserver1->nameserver_hostname = 'ferry.internal.one.test';
        $nameserver2->nameserver_hostname = 'ferry.internal.two.test';
        $nameserver3->nameserver_hostname = 'ferry.internal.three.test';

        $nameserver1->save();
        $nameserver2->save();
        $nameserver3->save();
    }

    private function wallet(Customer $customer): void
    {
        $wallet = new CustomerWallet();
        $wallet->customer_id = $customer->id;
        $wallet->amount = 100_39;
        $wallet->save();
    }
}
